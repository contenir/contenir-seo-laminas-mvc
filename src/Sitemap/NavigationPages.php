<?php

declare(strict_types=1);

namespace Contenir\Seo\Laminas\Mvc\Sitemap;

use Contenir\Seo\Laminas\Mvc\SitemapSourceInterface;
use Contenir\Seo\Sitemap\Page;
use DateTimeInterface;
use Laminas\Navigation\AbstractContainer;
use Laminas\Navigation\Page\AbstractPage;
use Override;
use RecursiveIteratorIterator;

use function array_filter;
use function array_values;
use function date_create_immutable;
use function is_array;
use function is_string;

/**
 * Every page of a navigation container, hidden from the menu or not, as
 * sitemap pages. Each page's own properties add to it:
 *
 * - lastmod: when its record last changed (a date string or DateTimeInterface);
 * - images: its image URLs or paths (a list, or one string);
 * - robots: its robots value, such as "noindex";
 * - workflow: its workflow (page, news, redirect…), which a site can leave out.
 *
 * @api
 */
final readonly class NavigationPages implements SitemapSourceInterface
{
    /**
     * @param AbstractContainer<AbstractPage> $navigation
     */
    public function __construct(
        private AbstractContainer $navigation,
    ) {}

    /**
     * @return list<string>
     *
     * @mago-expect analysis:mixed-assignment Page properties are untyped; each is checked here.
     */
    private static function images(mixed $images): array
    {
        $images = is_string($images) ? [$images] : $images;

        return is_array($images) ? array_values(array_filter($images, is_string(...))) : [];
    }

    private static function modified(mixed $value): ?DateTimeInterface
    {
        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        if (! is_string($value) || '' === $value) {
            return null;
        }

        $date = date_create_immutable($value);

        return false === $date ? null : $date;
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && '' !== $value ? $value : null;
    }

    /**
     * @return list<Page>
     */
    #[Override]
    public function pages(): array
    {
        $pages = [];
        foreach (new RecursiveIteratorIterator($this->navigation, RecursiveIteratorIterator::SELF_FIRST) as $page) {
            $pages[] = new Page(
                (string) $page->getHref(),
                self::modified($page->get('lastmod')),
                self::images($page->get('images')),
                self::text($page->get('robots')),
                self::text($page->get('workflow')),
            );
        }

        return $pages;
    }
}
