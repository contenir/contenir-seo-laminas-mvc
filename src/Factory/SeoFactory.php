<?php

declare(strict_types=1);

namespace Contenir\Seo\Laminas\Mvc\Factory;

use Contenir\Seo\Laminas\Mvc\View\Helper\Seo;
use Contenir\Seo\SeoConfig;
use Laminas\Http\Request;
use Laminas\Mvc\Application;
use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\View\Helper\HeadMeta;
use Laminas\View\Helper\HeadTitle;
use Laminas\View\HelperPluginManager;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Builds seo() with the site's SeoConfig, the head helpers, and the
 * application's MVC event, which says which route matched and which host
 * was asked for. Without an application (a console script, a test), an
 * event of its own carries the "Request" service when there is one.
 *
 * @api
 */
final class SeoFactory
{
    /**
     * @throws ContainerExceptionInterface
     *
     * @mago-expect analysis:mixed-assignment Container services are untyped; each is checked here.
     */
    private static function event(ContainerInterface $container): MvcEvent
    {
        $application = $container->has('Application') ? $container->get('Application') : null;
        $event       = $application instanceof Application ? $application->getMvcEvent() : null;
        if ($event instanceof MvcEvent) {
            return $event;
        }

        $event   = new MvcEvent();
        $request = $container->has('Request') ? $container->get('Request') : null;
        if ($request instanceof Request) {
            $event->setRequest($request);
        }

        return $event;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ServiceNotCreatedException when a service has the wrong type.
     */
    public function __invoke(ContainerInterface $container): Seo
    {
        $helpers = Services::get($container, 'ViewHelperManager', HelperPluginManager::class);

        return new Seo(
            Services::get($container, SeoConfig::class, SeoConfig::class),
            $helpers->get(HeadTitle::class),
            $helpers->get(HeadMeta::class),
            self::event($container),
        );
    }
}
