<?php

namespace Georgeff\Kernel\Contract;

/**
 * Extension point for custom deployment environments: implement directly (or extend
 * AbstractEnvironment) to add environments beyond the shipped Local/Development/Staging/
 * Testing/Production set.
 */
interface EnvironmentInterface
{
    /**
     * The canonical string identity for this environment, e.g. 'production'.
     */
    public function getValue(): string;

    /**
     * Whether this environment's value matches any of the given values.
     */
    public function is(string ...$values): bool;
}
