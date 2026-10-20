<?php

declare(strict_types=1);

namespace Contenir\Seo\Laminas\Mvc\Factory;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

use function get_debug_type;
use function sprintf;

/**
 * Takes services out of a container with the type a factory needs.
 *
 * @internal
 */
final class Services
{
    /**
     * @template T of object
     *
     * @param class-string<T> $type
     *
     * @return T
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ServiceNotCreatedException when the service has another type.
     *
     * @mago-expect analysis:mixed-assignment Container services are untyped; the type is checked here.
     */
    public static function get(ContainerInterface $container, string $name, string $type): object
    {
        $service = $container->get($name);
        if (! $service instanceof $type) {
            throw new ServiceNotCreatedException(sprintf(
                'Service "%s" must be a %s, %s given',
                $name,
                $type,
                get_debug_type($service),
            ));
        }

        return $service;
    }
}
