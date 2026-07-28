<?php

namespace Georgeff\Kernel\Environment;

use Georgeff\Kernel\Contract\EnvironmentInterface;

/**
 * Base class for EnvironmentInterface implementations: extend this and implement
 * getValue() to add a custom environment; is() is provided for free.
 */
abstract class AbstractEnvironment implements EnvironmentInterface
{
    public function is(string ...$values): bool
    {
        return in_array($this->getValue(), $values, true);
    }
}
