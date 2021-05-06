<?php

namespace GinoPane\BlogTaxonomy\Classes;

use ArrayAccess;
use Cms\Classes\Page;
use Cms\Classes\Theme;
use Cms\Classes\Controller;
use Winter\Blog\Models\Post;
use Cms\Classes\ComponentBase;
use Winter\Blog\Models\Category;
use Winter\Storm\Database\Collection;

/**
 * Class ComponentAbstract
 *
 * @package GinoPane\BlogTaxonomy\Classes
 */
abstract class ComponentAbstract extends ComponentBase
{
    /**
     * Reference to the page name for linking to posts
     *
     * @var string
     */
    protected $postPage;

    /**
     * Reference to the page name for linking to categories
     *
     * @var string
     */
    protected $categoryPage;

    /**
     * @param Collection $items
     * @param string $urlPage
     * @param Controller $controller
     * @param array $modelUrlParams
     */
    protected function setUrls(
        Collection $items,
        string $urlPage,
        Controller $controller,
        array $modelUrlParams = array()
    ) {
        if ($items) {
            foreach ($items as $item) {
                $item->setUrl($urlPage, $controller, $modelUrlParams);
            }
        }
    }

    /**
     * Set Urls to posts
     *
     * @param ArrayAccess $posts
     */
    protected function setPostUrls(ArrayAccess $posts)
    {
        // Add a "url" helper attribute for linking to each post and category
        if (!empty($this->postPage) && $posts && $posts->count()) {
            $posts->each(function ($post) {
                $this->setPostUrl($post);

                if (!empty($this->categoryPage) && $post->categories->count()) {
                    $post->categories->each(function ($category) {
                        /** @var Category $category */
                        $category->setUrl(
                            $this->categoryPage,
                            $this->controller
                        );
                    });
                }
            });
        }
    }

    protected function setPostUrl(Post $post)
    {
        $post->setUrl(
            $this->postPage,
            $this->controller
        );
    }

    /**
     * A helper function to get the real URL parameter name. For example, slug for posts
     * can be injected as :post into URL. Real argument is necessary if you want to generate
     * valid URLs for such pages
     *
     * @param ComponentBase|null $component
     * @param string $name
     *
     * @return string|null
     */
    protected function urlProperty(ComponentBase $component = null, string $name = '')
    {
        $property = null;

        if ($component !== null && ($property = $component->property($name))) {
            preg_match('/{{ :([^ ]+) }}/', $property, $matches);

            if (isset($matches[1])) {
                $property = $matches[1];
            }
        } else {
            $property = $name;
        }

        return $property;
    }

    /**
     * Returns page property defaulting to the value from defineProperties() array with fallback
     * to explicitly passed default value
     *
     * @param string $property
     * @param $default
     *
     * @return mixed
     */
    public function getProperty(string $property, $default = null)
    {
        return $this->property($property, $this->defineProperties()[$property]['default'] ?? $default);
    }

    /**
     * @param string $componentName
     * @param string $page
     * @return ComponentBase|null
     */
    protected function getComponent(string $componentName, string $page)
    {
        $component = null;

        $page = Page::load(Theme::getActiveTheme(), $page);

        if ($page !== null) {
            $component = $page->getComponent($componentName);
        }

        return $component;
    }
}
