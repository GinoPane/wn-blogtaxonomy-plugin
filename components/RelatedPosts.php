<?php

namespace GinoPane\BlogTaxonomy\Components;

use DB;
use Cms\Classes\Page;
use Winter\Blog\Models\Post;
use GinoPane\BlogTaxonomy\Plugin;
use GinoPane\BlogTaxonomy\Models\Tag;
use Winter\Storm\Database\Collection;
use GinoPane\BlogTaxonomy\Models\ModelAbstract;
use GinoPane\BlogTaxonomy\Classes\PostListAbstract;
use GinoPane\BlogTaxonomy\Classes\ComponentAbstract;
use GinoPane\BlogTaxonomy\Classes\TranslateArrayTrait;
use GinoPane\BlogTaxonomy\Classes\PostListFiltersTrait;

/**
 * Class RelatedPosts
 *
 * @package GinoPane\BlogTaxonomy\Components
 */
class RelatedPosts extends ComponentAbstract
{
    const NAME = 'relatedPosts';

    use TranslateArrayTrait;
    use PostListFiltersTrait;

    /**
     * @var Collection | array
     */
    public $posts = [];

    /**
     * Reference to the page name for linking to posts
     *
     * @var string
     */
    public $postPage;

    /**
     * If the post list should be ordered by another attribute
     *
     * @var string
     */
    public $orderBy;

    /**
     * Limits the number of records to display
     *
     * @var int
     */
    public $limit;

    /**
     * Component Registration
     *
     * @return  array
     */
    public function componentDetails(): array
    {
        return [
            'name'        => Plugin::LOCALIZATION_KEY . 'components.related_posts.name',
            'description' => Plugin::LOCALIZATION_KEY . 'components.related_posts.description'
        ];
    }

    /**
     * Component Properties
     *
     * @return  array
     */
    public function defineProperties(): array
    {
        return array_merge([
            'slug' => [
                'title'             => Plugin::LOCALIZATION_KEY . 'components.related_posts.post_slug_title',
                'description'       => Plugin::LOCALIZATION_KEY . 'components.related_posts.post_slug_description',
                'default'           => '{{ :slug }}',
                'type'              => 'string'
            ],

            'limit' => [
                'title'             => Plugin::LOCALIZATION_KEY . 'components.related_posts.limit_title',
                'description'       => Plugin::LOCALIZATION_KEY . 'components.related_posts.limit_description',
                'type'              => 'string',
                'default'           => '0',
                'validationPattern' => '^[0-9]+$',
                'validationMessage' => Plugin::LOCALIZATION_KEY . 'components.related_posts.limit_validation_message',
                'showExternalParam' => false
            ],

            'orderBy' => [
                'title'       => 'winter.blog::lang.settings.posts_order',
                'description' => 'winter.blog::lang.settings.posts_order_description',
                'type'        => 'dropdown',
                'default'     => 'published_at asc',
                'showExternalParam' => false
            ],

            'postPage' => [
                'group'       => Plugin::LOCALIZATION_KEY . 'components.related_posts.links_group',
                'title'       => 'winter.blog::lang.settings.posts_post',
                'description' => 'winter.blog::lang.settings.posts_description',
                'type'        => 'dropdown',
                'default'     => 'blog/post',
            ],
        ], $this->getPostFilterProperties());
    }

    /**
     * @see PostListAbstract::$postAllowedSortingOptions
     *
     * @return mixed
     */
    public function getOrderByOptions()
    {
        $order = $this->translate(
            array_merge(
                [
                    'relevance asc' => Plugin::LOCALIZATION_KEY . 'order_options.relevance_asc',
                    'relevance desc' => Plugin::LOCALIZATION_KEY . 'order_options.relevance_desc'
                ],
                PostListAbstract::$postAllowedSortingOptions
            )
        );

        asort($order);

        return $order;
    }

    private function prepareVars()
    {
        $this->orderBy = $this->page['orderBy'] = $this->property('orderBy');

        // Page links
        $this->postPage = $this->page['postPage' ] = $this->property('postPage');

        // Exceptions
        $this->populateFilters();
    }

    public function getPostPageOptions()
    {
        return Page::sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
    }

    /**
     * Load post and start building query for related posts
     */
    public function onRun()
    {
        //Prepare vars
        $this->prepareVars();

        $this->posts = $this->loadRelatedPosts();
    }

    /**
     * Load related posts by common tags
     *
     * @return mixed
     */
    private function loadRelatedPosts()
    {
        $post = Post::with('tags');

        ModelAbstract::whereTranslatableProperty($post, 'slug', $this->property('slug'));

        $post = $post->first();

        if (!$post || (!$tagIds = $post->tags->lists('id'))) {
            return null;
        }

        $query = Post::isPublished()
            ->where('id', '<>', $post->id)
            ->whereHas('tags', function ($tag) use ($tagIds) {
                $tag->whereIn('id', $tagIds);
            })
            ->with('tags');

        $this->handlePostFilters($query);
        $this->queryOrderBy($query, $tagIds);

        if ($take = (int)$this->property('limit')) {
            $query->take($take);
        }

        $posts = $query->get();

        $this->setPostUrls($posts);

        return $posts;
    }

    /**
     * @param $query
     * @param $tagIds
     */
    private function queryOrderBy($query, $tagIds)
    {
        if (array_key_exists($this->orderBy, $this->getOrderByOptions())) {
            if ($this->orderBy === 'random') {
                $query->inRandomOrder();
            } else {
                list($sortField, $sortDirection) = explode(' ', $this->orderBy);

                if ($sortField === 'relevance') {
                    $sortField = DB::raw(
                        sprintf(
                            '(
                                select count(*)
                                from `%1$s`
                                where
                                `%1$s`.`%2$s_type` = \'Winter\Blog\Models\Post\'
                                and `%1$s`.`%2$s_id` = `rainlab_blog_posts`.`id`
                                and `%1$s`.`tag_id` in (\'%3$s\')
                            )',
                            Tag::PIVOT_TABLE,
                            Tag::PIVOT_COLUMN,
                            implode('\', \'', $tagIds)
                        )
                    );
                }

                $query->orderBy($sortField, $sortDirection);
            }
        }
    }
}
