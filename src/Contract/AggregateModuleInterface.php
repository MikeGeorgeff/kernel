<?php

namespace Georgeff\Kernel\Contract;

/**
 * Adds composition to ModuleInterface: a module can return other modules to be added
 * alongside it, expanded recursively during moduleLoad. Composed-in modules can themselves
 * be composite; the existing module-dedup-by-class-name guard prevents accidental cycles.
 */
interface AggregateModuleInterface extends ModuleInterface
{
    /**
     * @return ModuleInterface[]
     */
    public function modules(EnvironmentInterface $env): array;
}
