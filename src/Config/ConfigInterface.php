<?php

namespace Georgeff\Kernel\Config;

interface ConfigInterface
{
    public function isEmpty(): bool;

    public function has(string $name): bool;

    public function get(string $name, mixed $default = null): mixed;

    /**
     * Returns the array value at $name as a nested ConfigInterface, or an empty
     * ConfigInterface if $name does not exist.
     *
     * @throws \Georgeff\Kernel\Exception\KernelExceptionInterface if the value exists
     *     but is not an array with non-numeric string keys
     */
    public function branch(string $name): ConfigInterface;
}
