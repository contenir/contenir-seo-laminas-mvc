<?php

declare(strict_types=1);

namespace Contenir\Seo\Laminas\Mvc\Tests\TestAsset;

use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Override;

/**
 * Stands in for laminas-mvc's default listeners, which a bootstrapped
 * application asks for but these tests don't need.
 */
final class NoListener extends AbstractListenerAggregate
{
    /**
     * @param int $priority
     */
    #[Override]
    public function attach(EventManagerInterface $events, $priority = 1): void {}
}
