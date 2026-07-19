<?php

namespace Georgeff\Kernel\Contract;

use Psr\Container\ContainerInterface;

interface ContainerBuilderInterface
{
    /**
     * @param callable(ContainerInterface): mixed $factory
     * @param string[]                            $aliases
     */
    public function register(string $id, callable $factory, bool $shared = false, array $aliases = []): void;

    /**
     * @param callable(string): void $callback
     */
    public function onResolving(callable $callback): void;

    /**
     * @param callable(string, mixed): void $callback
     */
    public function onResolved(callable $callback): void;

    public function getContainer(): ContainerInterface;
}
