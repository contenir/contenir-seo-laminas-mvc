<?php

declare(strict_types=1);

namespace Contenir\Seo\Laminas\Mvc;

use Contenir\Seo\Laminas\Mvc\Controller\SitemapController;
use Contenir\Seo\Laminas\Mvc\Factory\HideFromSearchListenerFactory;
use Contenir\Seo\Laminas\Mvc\Factory\SeoConfigFactory;
use Contenir\Seo\Laminas\Mvc\Factory\SeoFactory;
use Contenir\Seo\Laminas\Mvc\Factory\SitemapControllerFactory;
use Contenir\Seo\Laminas\Mvc\Listener\HideFromSearchListener;
use Contenir\Seo\Laminas\Mvc\View\Helper\Seo;
use Contenir\Seo\SeoConfig;
use Laminas\Router\Http\Literal;
use Laminas\Router\Http\Regex;

/**
 * The module's configuration: the sitemap and robots.txt routes, which keep
 * contenir-sitemap-laminas-mvc's names and paths, their controller, the
 * seo() view helper, and the services behind them.
 *
 * @api
 */
final class ConfigProvider
{
    /**
     * @return array<string, array<string, mixed>>
     */
    private static function routes(): array
    {
        $controller = ['controller' => SitemapController::class];

        return [
            'sitemap'      => [
                'type'    => Literal::class,
                'options' => ['route' => '/sitemap.xml', 'defaults' => [...$controller, 'action' => 'index']],
            ],
            'sitemap-page' => [
                'type'    => Regex::class,
                'options' => [
                    'regex'    => '/sitemap-(?<page>[1-9][0-9]*)\.xml',
                    'spec'     => '/sitemap-%page%.xml',
                    'defaults' => [...$controller, 'action' => 'page'],
                ],
            ],
            'robots'       => [
                'type'    => Literal::class,
                'options' => ['route' => '/robots.txt', 'defaults' => [...$controller, 'action' => 'robots']],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function __invoke(): array
    {
        return [
            'router'          => ['routes' => self::routes()],
            'controllers'     => [
                'factories' => [SitemapController::class => SitemapControllerFactory::class],
            ],
            'service_manager' => [
                'factories' => [
                    SeoConfig::class              => SeoConfigFactory::class,
                    HideFromSearchListener::class => HideFromSearchListenerFactory::class,
                ],
            ],
            'view_helpers'    => [
                'aliases'   => ['seo' => Seo::class, 'Seo' => Seo::class],
                'factories' => [Seo::class => SeoFactory::class],
            ],
        ];
    }
}
