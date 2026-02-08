<?php

namespace Georgeff\Kernel;

use Psr\Container\ContainerInterface;

interface KernelInterface
{
    /**
     * Boot the kernel
     *
     * @return void
     */
    public function boot(): void;

    /**
     * Indicates if the kernel is currently booting
     *
     * @return bool
     */
    public function isBooting(): bool;

    /**
     * Indicates if the kernel has been booted
     *
     * @return bool
     */
    public function isBooted(): bool;

    /**
     * Get the kernel environment
     *
     * @return string
     */
    public function getEnvironment(): string;

    /**
     * Indicates if debug is enabled
     *
     * @return bool
     */
    public function isDebug(): bool;

    /**
     * Add a container definition
     *
     * @param string   $id
     * @param callable $factory
     * @param bool     $shared
     * @param string[] $aliases
     *
     * @return static
     */
    public function addDefinition(string $id, callable $factory, bool $shared = false, array $aliases = []): static;

    /**
     * Get the container
     *
     * @return \Psr\Container\ContainerInterface
     */
    public function getContainer(): ContainerInterface;
}
