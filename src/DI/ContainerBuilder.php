<?php

namespace Georgeff\Kernel\DI;

use Georgeff\Container\Container;
use Psr\Container\ContainerInterface;
use Georgeff\Kernel\Contract\ContainerBuilderInterface;

/**
 * @internal
 */
final class ContainerBuilder implements ContainerBuilderInterface
{
    private Container $container;

    public function __construct()
    {
        $this->container = new Container();
    }

    public function register(string $id, callable $factory, bool $shared = false, array $aliases = []): void
    {
        $this->container->add($id, $factory, $shared);

        foreach ($aliases as $alias) {
            $this->container->addAlias($id, $alias);
        }
    }

    public function onResolving(callable $callback): void
    {
        $this->container->onResolving($callback);
    }

    public function onResolved(callable $callback): void
    {
        $this->container->afterResolved($callback);
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }
}
