<?php

namespace Georgeff\Kernel\Contract;

interface EnvironmentInterface
{
    public function getValue(): string;

    public function is(string ...$values): bool;
}
