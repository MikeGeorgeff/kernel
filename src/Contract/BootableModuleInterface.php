<?php

namespace Georgeff\Kernel\Contract;

use Psr\Container\ContainerInterface;

/**
 * Adds a boot-time hook to ModuleInterface: implement when a module needs to act on
 * the built container itself, not just register definitions.
 */
interface BootableModuleInterface extends ModuleInterface
{
    /**
     * Called during the moduleBoot phase, after the container has been built.
     */
    public function boot(ContainerInterface $container): void;
}
