<?php

namespace Georgeff\Kernel\Environment;

use Georgeff\Kernel\Contract\EnvironmentInterface;

abstract class AbstractEnvironment implements EnvironmentInterface
{
    public function is(string ...$values): bool
    {
        return in_array($this->getValue(), $values, true);
    }
}
