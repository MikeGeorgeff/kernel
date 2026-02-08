<?php

namespace Georgeff\Kernel;

use Georgeff\Container\Container;
use Psr\Container\ContainerInterface;

final class DefaultServiceRegistrar implements ServiceRegistrar
{
    private Container $container;

    public function __construct()
    {
        $this->container = new Container();
    }

    /**
     * @inheritdoc
     */
    public function register(string $id, callable $factory, bool $shared = false, array $aliases = []): void
    {
        $this->container->add($id, $factory, $shared);

        foreach ($aliases as $alias) {
            $this->container->addAlias($id, $alias);
        }
    }

    /**
     * @inheritdoc
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }
}
