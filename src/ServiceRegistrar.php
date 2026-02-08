<?php

namespace Georgeff\Kernel;

use Psr\Container\ContainerInterface;

interface ServiceRegistrar
{
    /**
     * Register a service with the container
     *
     * @param string   $id
     * @param callable $factory
     * @param bool     $shared
     * @param string[] $aliases
     *
     * @return void
     */
    public function register(string $id, callable $factory, bool $shared = false, array $aliases = []): void;

    /**
     * Get the underlying container instance
     *
     * @return \Psr\Container\ContainerInterface
     */
    public function getContainer(): ContainerInterface;
}
