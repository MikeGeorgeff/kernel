<?php

namespace Georgeff\Kernel;

interface ResolvingAwareServiceRegistrar extends ServiceRegistrar
{
    /**
     * @param callable(string, mixed): void $callback
     */
    public function afterResolved(callable $callback): void;
}
