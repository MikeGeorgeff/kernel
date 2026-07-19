<?php

namespace Georgeff\Kernel\Config;

interface ConfigInterface
{
    public function has(string $name): bool;

    public function get(string $name, mixed $default = null): mixed;
}
