<?php

declare(strict_types=1);

namespace Contenir\Seo\Laminas\Mvc\Factory;

use Contenir\Seo\Laminas\Mvc\Controller\SitemapController;
use Contenir\Seo\Laminas\Mvc\Sitemap\NavigationPages;
use Contenir\Seo\Laminas\Mvc\SitemapSourceInterface;
use Contenir\Seo\SeoConfig;
use Laminas\Navigation\AbstractContainer;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

use function array_filter;
use function array_values;
use function is_array;
use function is_string;

/**
 * Builds the SitemapController with the site's SeoConfig and its sitemap's
 * sources: the "cms" navigation container, when the site has one, then
 * each service named in contenir_seo.sitemap_sources.
 *
 * @api
 */
final class SitemapControllerFactory
{
    /**
     * @return list<string>
     *
     * @throws ContainerExceptionInterface
     *
     * @mago-expect analysis:mixed-assignment The application's config is untyped; each part is checked here.
     */
    private static function sourceNames(ContainerInterface $container): array
    {
        $config  = $container->has('config') ? $container->get('config') : [];
        $options = is_array($config) && is_array($config['contenir_seo'] ?? null) ? $config['contenir_seo'] : [];
        $names   = is_array($options['sitemap_sources'] ?? null) ? $options['sitemap_sources'] : [];

        return array_values(array_filter($names, is_string(...)));
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ServiceNotCreatedException when a service has the wrong type.
     */
    public function __invoke(ContainerInterface $container): SitemapController
    {
        $sources = [];
        if ($container->has(SitemapController::CONTAINER)) {
            $sources[] = new NavigationPages(
                Services::get($container, SitemapController::CONTAINER, AbstractContainer::class),
            );
        }

        foreach (self::sourceNames($container) as $name) {
            $sources[] = Services::get($container, $name, SitemapSourceInterface::class);
        }

        return new SitemapController(Services::get($container, SeoConfig::class, SeoConfig::class), $sources);
    }
}
