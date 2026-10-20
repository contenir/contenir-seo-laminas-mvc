<?php

declare(strict_types=1);

namespace Contenir\Seo\Laminas\Mvc\Listener;

use Contenir\Seo\SeoConfig;
use Contenir\Seo\Visibility\Visibility;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Http\Request;
use Laminas\Http\Response;
use Laminas\Mvc\MvcEvent;
use Override;

/**
 * Sends X-Robots-Tag: noindex, nofollow on every response of a copy of the
 * site that is hidden from search engines, pages, files served through the
 * application and errors alike.
 *
 * @api
 */
final class HideFromSearchListener extends AbstractListenerAggregate
{
    public const string HEADER = 'X-Robots-Tag';

    public function __construct(
        private readonly SeoConfig $config,
    ) {}

    /**
     * @param int $priority
     */
    #[Override]
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_FINISH, $this->onFinish(...), $priority);
    }

    public function onFinish(MvcEvent $event): void
    {
        $request  = $event->getRequest();
        $response = $event->getResponse();
        if (! $request instanceof Request || ! $response instanceof Response) {
            return;
        }

        if ($this->config->visibility->hides($this->config->site, (string) $request->getUri()->getHost())) {
            $response->getHeaders()->addHeaderLine(self::HEADER, Visibility::HIDDEN->value);
        }
    }
}
