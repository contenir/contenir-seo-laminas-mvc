<?php

declare(strict_types=1);

namespace Contenir\Seo\Laminas\Mvc\Tests\Trait;

use Contenir\Seo\Laminas\Mvc\ConfigProvider;
use Contenir\Seo\Laminas\Mvc\Controller\SitemapController;
use Contenir\Seo\Laminas\Mvc\Factory\SitemapControllerFactory;
use Contenir\Seo\Laminas\Mvc\Tests\TestAsset\ArrayContainer;
use Contenir\Seo\Laminas\Mvc\Tests\TestAsset\ArticleSource;
use Contenir\Seo\SeoConfig;
use Contenir\Seo\Sitemap\Page;
use DateTimeImmutable;
use Laminas\Http\Request;
use Laminas\Http\Response;
use Laminas\Mvc\MvcEvent;
use Laminas\Navigation\Navigation;
use Laminas\Router\Http\TreeRouteStack;
use Laminas\Router\RouteMatch;

/**
 * A minimal laminas-mvc application: the module's routes, a "cms"
 * navigation container with pages hidden from the menu, and a news source,
 * dispatching requests to the sitemap controller.
 */
trait SitemapApplicationTrait
{
    /**
     * The "cms" container: pages hidden from the menu, a redirect, a noindex
     * page and a link to another site.
     */
    private static function navigation(): Navigation
    {
        return new Navigation([
            ['label' => 'Home', 'uri' => '/', 'lastmod' => '2026-10-20 08:00:00'],
            [
                'label'  => 'About',
                'uri'    => '/about',
                'images' => ['/img/team.jpg'],
                'pages'  => [['label' => 'Team', 'uri' => '/about/team', 'visible' => false]],
            ],
            ['label' => 'Old page', 'uri' => '/old', 'workflow' => 'redirect'],
            ['label' => 'Thanks', 'uri' => '/thanks', 'visible' => false, 'robots' => 'noindex'],
            ['label' => 'Shop', 'uri' => 'https://shop.example.net/'],
        ]);
    }

    private static function services(SeoConfig $config): ArrayContainer
    {
        return new ArrayContainer([
            SeoConfig::class => $config,
            'cms'            => self::navigation(),
            'config'         => ['contenir_seo' => ['sitemap_sources' => ['news', 3]]],
            'news'           => new ArticleSource([
                new Page('/news/hello', new DateTimeImmutable('2026-10-01T00:00:00+00:00')),
            ]),
        ]);
    }

    /**
     * Dispatches $url to the controller the module's factory builds.
     */
    private function dispatch(string $url, SeoConfig $config): MvcEvent
    {
        return $this->dispatchTo((new SitemapControllerFactory())(self::services($config)), $url);
    }

    private function dispatchTo(SitemapController $controller, string $url): MvcEvent
    {
        $request = new Request();
        $request->setUri($url);

        /** @var array{router: array{routes: array<string, mixed>}} $module */
        $module     = (new ConfigProvider())();
        $router     = TreeRouteStack::factory(['routes' => $module['router']['routes']]);
        $routeMatch = $router->match($request);
        self::assertInstanceOf(RouteMatch::class, $routeMatch);

        $event = new MvcEvent();
        $event->setRouter($router)->setRouteMatch($routeMatch);
        $controller->setEvent($event);
        $controller->dispatch($request, new Response());

        return $event;
    }
}
