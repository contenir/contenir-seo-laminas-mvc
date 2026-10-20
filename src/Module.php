<?php

declare(strict_types=1);

namespace Contenir\Seo\Laminas\Mvc;

use Contenir\Seo\Laminas\Mvc\Listener\HideFromSearchListener;
use Laminas\Mvc\MvcEvent;
use Psr\Container\ContainerExceptionInterface;

/**
 * Registers the sitemap and robots.txt routes, the seo() view helper, and a
 * listener that sends X-Robots-Tag on a hidden copy of the site.
 *
 * @api
 */
final class Module
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function getConfig(): array
    {
        return (new ConfigProvider())();
    }

    /**
     * @throws ContainerExceptionInterface
     */
    public function onBootstrap(MvcEvent $event): void
    {
        $application = $event->getApplication();
        $application->getServiceManager()
            ->get(HideFromSearchListener::class)
            ->attach($application->getEventManager());
    }
}
