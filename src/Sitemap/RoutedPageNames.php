<?php

declare(strict_types=1);

namespace Contenir\Seo\Laminas\Mvc\Sitemap;

use Contenir\Seo\SeoConfig;
use Laminas\Navigation\Exception\ExceptionInterface as NavigationException;
use Laminas\Router\Exception\ExceptionInterface as RouterException;

/**
 * The site's pages' names by address, for a page's breadcrumb trail: each step
 * is named as the site's navigation names it, and a step that is no page of the
 * site is left out. Nothing, so that each step is named from its path, where
 * the site's records switch is off or the navigation cannot be read.
 *
 * @api
 */
final readonly class RoutedPageNames
{
    public function __construct(
        private SeoConfig $config,
        private NavigationPages $navigation,
    ) {}

    /**
     * @return ?array<string, string>
     */
    public function names(): ?array
    {
        if (! $this->config->records) {
            return null;
        }

        try {
            return $this->navigation->names();
        } catch (NavigationException|RouterException) {
            return null;
        }
    }
}
