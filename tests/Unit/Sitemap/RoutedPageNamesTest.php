<?php

declare(strict_types=1);

namespace Contenir\Seo\Laminas\Mvc\Tests\Unit\Sitemap;

use Contenir\Seo\Laminas\Mvc\Sitemap\NavigationPages;
use Contenir\Seo\Laminas\Mvc\Sitemap\RoutedPageNames;
use Contenir\Seo\SeoConfig;
use Laminas\Navigation\Navigation;
use Laminas\Router\Http\TreeRouteStack;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RoutedPageNamesTest extends TestCase
{
    #[Test]
    public function givesNoNamesWhenARouteCannotBeAssembled(): void
    {
        $names = new RoutedPageNames(
            new SeoConfig(records: true),
            new NavigationPages(new Navigation([
                ['type' => 'mvc', 'label' => 'Stay', 'route' => 'missing', 'router' => new TreeRouteStack()],
            ])),
        );

        static::assertNull($names->names());
    }

    #[Test]
    public function givesNoNamesWhenTheNavigationCannotBeRead(): void
    {
        $names = new RoutedPageNames(
            new SeoConfig(records: true),
            new NavigationPages(new Navigation([['label' => 'Stay', 'route' => 'stay']])),
        );

        static::assertNull($names->names());
    }

    #[Test]
    public function givesNoNamesWhenTheRecordsSwitchIsOff(): void
    {
        $names = new RoutedPageNames(
            new SeoConfig(records: false),
            new NavigationPages(new Navigation([['label' => 'Stay', 'uri' => '/stay']])),
        );

        static::assertNull($names->names());
    }

    #[Test]
    public function namesThePagesWhenTheRecordsSwitchIsOn(): void
    {
        $names = new RoutedPageNames(
            new SeoConfig(records: true),
            new NavigationPages(new Navigation([['label' => 'Stay', 'uri' => '/stay']])),
        );

        static::assertSame(['/stay' => 'Stay'], $names->names());
    }
}
