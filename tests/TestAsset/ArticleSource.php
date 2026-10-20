<?php

declare(strict_types=1);

namespace Contenir\Seo\Laminas\Mvc\Tests\TestAsset;

use Contenir\Seo\Laminas\Mvc\SitemapSourceInterface;
use Contenir\Seo\Sitemap\Page;
use Override;

/**
 * News articles, as a site's own sitemap source would list them.
 */
final readonly class ArticleSource implements SitemapSourceInterface
{
    /**
     * @param list<Page> $pages
     */
    public function __construct(
        private array $pages,
    ) {}

    #[Override]
    public function pages(): iterable
    {
        yield from $this->pages;
    }
}
