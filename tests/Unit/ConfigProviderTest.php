<?php

declare(strict_types=1);

namespace Contenir\Seo\Laminas\Mvc\Tests\Unit;

use Contenir\Seo\Laminas\Mvc\ConfigProvider;
use Contenir\Seo\Laminas\Mvc\Controller\SitemapController;
use Contenir\Seo\Laminas\Mvc\Factory\HideFromSearchListenerFactory;
use Contenir\Seo\Laminas\Mvc\Factory\SeoConfigFactory;
use Contenir\Seo\Laminas\Mvc\Factory\SeoFactory;
use Contenir\Seo\Laminas\Mvc\Factory\SitemapControllerFactory;
use Contenir\Seo\Laminas\Mvc\Listener\HideFromSearchListener;
use Contenir\Seo\Laminas\Mvc\Module;
use Contenir\Seo\Laminas\Mvc\View\Helper\Seo;
use Contenir\Seo\SeoConfig;
use Laminas\Router\Http\Literal;
use Laminas\Router\Http\Regex;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ConfigProviderTest extends TestCase
{
    #[Test]
    public function keepsTheSitemapModulesRoutesAndAddsThePages(): void
    {
        static::assertSame(
            [
                'sitemap'      => [
                    'type'    => Literal::class,
                    'options' => [
                        'route'    => '/sitemap.xml',
                        'defaults' => ['controller' => SitemapController::class, 'action' => 'index'],
                    ],
                ],
                'sitemap-page' => [
                    'type'    => Regex::class,
                    'options' => [
                        'regex'    => '/sitemap-(?<page>[1-9][0-9]*)\.xml',
                        'spec'     => '/sitemap-%page%.xml',
                        'defaults' => ['controller' => SitemapController::class, 'action' => 'page'],
                    ],
                ],
                'robots'       => [
                    'type'    => Literal::class,
                    'options' => [
                        'route'    => '/robots.txt',
                        'defaults' => ['controller' => SitemapController::class, 'action' => 'robots'],
                    ],
                ],
            ],
            (new ConfigProvider())()['router']['routes'] ?? null,
        );
    }

    #[Test]
    public function registersTheControllerServicesAndViewHelper(): void
    {
        $config = (new ConfigProvider())();

        static::assertSame(
            [
                ['factories' => [SitemapController::class => SitemapControllerFactory::class]],
                [
                    'factories' => [
                        SeoConfig::class              => SeoConfigFactory::class,
                        HideFromSearchListener::class => HideFromSearchListenerFactory::class,
                    ],
                ],
                [
                    'aliases'   => ['seo' => Seo::class, 'Seo' => Seo::class],
                    'factories' => [Seo::class => SeoFactory::class],
                ],
            ],
            [$config['controllers'] ?? null, $config['service_manager'] ?? null, $config['view_helpers'] ?? null],
        );
    }

    #[Test]
    public function servesTheProvidersConfigurationAsTheModules(): void
    {
        static::assertSame((new ConfigProvider())(), (new Module())->getConfig());
    }
}
