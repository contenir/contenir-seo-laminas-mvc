<?php

declare(strict_types=1);

namespace Contenir\Seo\Laminas\Mvc\Tests\Unit\Sitemap;

use Contenir\Seo\Laminas\Mvc\Sitemap\NavigationPages;
use Contenir\Seo\Sitemap\Page;
use DateTimeImmutable;
use Laminas\Navigation\Navigation;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[Group('unit')]
final class NavigationPagesTest extends TestCase
{
    #[Test]
    public function keepsADateObjectAsItIs(): void
    {
        $modified = new DateTimeImmutable('2026-10-01T00:00:00+10:00');

        $pages = (new NavigationPages(new Navigation([['uri' => '/', 'lastmod' => $modified]])))->pages();

        static::assertSame($modified, $pages[0]->modified ?? null);
    }

    #[Test]
    public function listsEveryPageWithItsPropertiesHiddenOrNot(): void
    {
        $modified = new DateTimeImmutable('2026-10-01T00:00:00+00:00');
        $pages    = (new NavigationPages(new Navigation([
            ['label' => 'Home', 'uri' => '/', 'lastmod' => '2026-10-20 08:00:00', 'images' => ['/a.jpg', 3, '/b.jpg']],
            [
                'label'    => 'About',
                'uri'      => '/about',
                'visible'  => false,
                'lastmod'  => $modified,
                'images'   => '/c.jpg',
                'robots'   => 'noindex',
                'workflow' => 'page',
                'pages'    => [['label' => 'Team', 'uri' => '/about/team', 'lastmod' => 'not a date', 'images' => 4]],
            ],
            ['label' => 'Blank', 'uri' => '/blank', 'lastmod' => '', 'robots' => '', 'workflow' => 5],
        ])))->pages();

        static::assertSame(
            [
                ['/', '2026-10-20 08:00:00', ['/a.jpg', '/b.jpg'], null, null],
                ['/about', '2026-10-01 00:00:00', ['/c.jpg'], 'noindex', 'page'],
                ['/about/team', null, [], null, null],
                ['/blank', null, [], null, null],
            ],
            array_map(
                static fn(Page $page): array => [
                    $page->url,
                    $page->modified?->format('Y-m-d H:i:s'),
                    $page->images,
                    $page->robots,
                    $page->workflow,
                ],
                $pages,
            ),
        );
    }
}
