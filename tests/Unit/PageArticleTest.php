<?php

declare(strict_types=1);

namespace Contenir\Seo\Laminas\Mvc\Tests\Unit;

use Contenir\Resource\Core\Metadata\PageMetadata;
use Contenir\Seo\Laminas\Mvc\PageArticle;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class PageArticleTest extends TestCase
{
    #[Test]
    public function aPageOfTypeArticleIsDescribedAsAnArticle(): void
    {
        static::assertSame(
            'Article',
            PageArticle::of(
                new PageMetadata('https://www.example.test/news', title: 'News', type: PageMetadata::ARTICLE),
            )?->type,
        );
    }

    #[Test]
    public function aPageWithNeitherATypeNorATitleHasNoMainNode(): void
    {
        static::assertSame(
            [null, null],
            [
                PageArticle::of(new PageMetadata('https://www.example.test/a', title: 'A')),
                PageArticle::of(new PageMetadata('https://www.example.test/a', schema: 'WebPage')),
            ],
        );
    }

    #[Test]
    public function aSchemaTypeWinsOverTheArticleOgType(): void
    {
        static::assertSame(
            'NewsArticle',
            PageArticle::of(new PageMetadata(
                'https://www.example.test/news',
                title: 'News',
                type: PageMetadata::ARTICLE,
                schema: 'NewsArticle',
            ))?->type,
        );
    }

    #[Test]
    public function theNodeCarriesThePagesOwnFacts(): void
    {
        $node = PageArticle::of(new PageMetadata(
            'https://www.example.test/news',
            title: 'News',
            description: 'Latest.',
            image: '/n.jpg',
            type: PageMetadata::ARTICLE,
        ));

        static::assertSame(
            ['https://www.example.test/news', 'News', 'Latest.', '/n.jpg'],
            [$node?->url, $node?->headline, $node?->description, $node?->image],
        );
    }
}
