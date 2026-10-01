<?php

namespace Georgeff\Kernel\Contract;

/**
 * Adds composition to ModuleInterface: a module can return other modules to be added
 * alongside it. Returned modules can themselves be aggregates.
 *
 * Each module class is added once. A returned module whose class is already present is
 * ignored rather than treated as an error, so several aggregates can return the same
 * dependency, and aggregates that return each other are safe. A module added directly
 * to the kernel takes precedence over the same class returned by an aggregate; between
 * aggregates, the first instance returned is kept.
 */
interface AggregateModuleInterface extends ModuleInterface
{
    /**
     * @return ModuleInterface[]
     */
    public function modules(EnvironmentInterface $env): array;
}
