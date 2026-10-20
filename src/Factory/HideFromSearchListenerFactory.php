<?php

declare(strict_types=1);

namespace Contenir\Seo\Laminas\Mvc\Factory;

use Contenir\Seo\Laminas\Mvc\Listener\HideFromSearchListener;
use Contenir\Seo\SeoConfig;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Builds the listener that hides a copy of the site, with its SeoConfig.
 *
 * @api
 */
final class HideFromSearchListenerFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ServiceNotCreatedException when the SeoConfig service has the wrong type.
     */
    public function __invoke(ContainerInterface $container): HideFromSearchListener
    {
        return new HideFromSearchListener(Services::get($container, SeoConfig::class, SeoConfig::class));
    }
}
