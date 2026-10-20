<?php

declare(strict_types=1);

namespace Contenir\Seo\Laminas\Mvc\Tests\Unit\Factory;

use Contenir\Seo\Laminas\Mvc\Factory\HideFromSearchListenerFactory;
use Contenir\Seo\Laminas\Mvc\Factory\SeoFactory;
use Contenir\Seo\Laminas\Mvc\Factory\SitemapControllerFactory;
use Contenir\Seo\Laminas\Mvc\Listener\HideFromSearchListener;
use Contenir\Seo\Laminas\Mvc\Tests\TestAsset\ArrayContainer;
use Contenir\Seo\SeoConfig;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

#[Group('unit')]
final class FactoriesTest extends TestCase
{
    /**
     * @return array<string, array{callable(ArrayContainer): object, array<string, mixed>, string}>
     */
    public static function wrongServices(): array
    {
        return [
            'the listener\'s SeoConfig'    => [
                new HideFromSearchListenerFactory(),
                [SeoConfig::class => new stdClass()],
                'Service "Contenir\Seo\SeoConfig" must be a Contenir\Seo\SeoConfig, stdClass given',
            ],
            'the helper\'s view helpers'   => [
                new SeoFactory(),
                [SeoConfig::class => new SeoConfig(), 'ViewHelperManager' => 'helpers'],
                'Service "ViewHelperManager" must be a Laminas\View\HelperPluginManager, string given',
            ],
            'the controller\'s navigation' => [
                new SitemapControllerFactory(),
                [SeoConfig::class => new SeoConfig(), 'cms' => []],
                'Service "cms" must be a Laminas\Navigation\AbstractContainer, array given',
            ],
            'a sitemap source'             => [
                new SitemapControllerFactory(),
                [
                    SeoConfig::class => new SeoConfig(),
                    'config'         => ['contenir_seo' => ['sitemap_sources' => ['news']]],
                    'news'           => new stdClass(),
                ],
                'Service "news" must be a Contenir\Seo\Laminas\Mvc\SitemapSourceInterface, stdClass given',
            ],
        ];
    }

    #[Test]
    public function buildsTheListenerWithTheSitesConfig(): void
    {
        static::assertInstanceOf(
            HideFromSearchListener::class,
            (new HideFromSearchListenerFactory())(new ArrayContainer([SeoConfig::class => new SeoConfig()])),
        );
    }

    /**
     * @param callable(ArrayContainer): object $factory
     * @param array<string, mixed>             $services
     */
    #[DataProvider('wrongServices')]
    #[Test]
    public function namesAServiceOfTheWrongType(callable $factory, array $services, string $message): void
    {
        $this->expectException(ServiceNotCreatedException::class);
        $this->expectExceptionMessage($message);

        $factory(new ArrayContainer($services));
    }
}
