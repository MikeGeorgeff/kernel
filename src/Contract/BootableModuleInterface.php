<?php

namespace Georgeff\Kernel\Contract;

use Psr\Container\ContainerInterface;

interface BootableModuleInterface extends ModuleInterface
{
    /**
     * Boot the module
     */
    public function boot(ContainerInterface $container): void;
}
