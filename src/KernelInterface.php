<?php

namespace Georgeff\Kernel;

use Psr\Container\ContainerInterface;
use Georgeff\Kernel\Module\ModuleInterface;
use Georgeff\Kernel\Module\ModuleRepositoryInterface;

interface KernelInterface
{
    /**
     * Boot the kernel
     *
     * @return void
     */
    public function boot(): void;

    /**
     * Shutdown the kernel
     *
     * @return void
     */
    public function shutdown(): void;

    /**
     * Indicates if the kernel has been booted
     *
     * @return bool
     */
    public function isBooted(): bool;

    /**
     * Indicates if the kernel has been shutdown
     *
     * @return bool
     */
    public function isShutdown(): bool;

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
     * Register a pre-boot callback
     *
     * @param callable(KernelInterface): void $callback
     *
     * @return static
     */
    public function onBooting(callable $callback): static;

    /**
     * Register a post-boot callback
     *
     * @param callable(KernelInterface): void $callback
     *
     * @return static
     */
    public function onBooted(callable $callback): static;

    /**
     * Register a pre-shutdown callback
     *
     * @param callable(KernelInterface): void $callback
     *
     * @return static
     */
    public function onShutdown(callable $callback): static;

    /**
     * Register a post shutdown callback
     *
     * @param callable(KernelInterface): void $callback
     *
     * @return static
     */
    public function afterShutdown(callable $callback): static;

    /**
     * Add a container definition
     *
     * @param string                               $id
     * @param callable(ContainerInterface): mixed  $factory
     * @param bool                                 $shared
     * @param string[]                             $aliases
     *
     * @return static
     */
    public function addDefinition(string $id, callable $factory, bool $shared = false, array $aliases = []): static;

    /**
     * Add a module to the kernel
     *
     * @param \Georgeff\Kernel\Module\ModuleInterface $module
     *
     * @return static
     */
    public function addModule(ModuleInterface $module): static;

    /**
     * Add a module repository to the kernel
     *
     * @param \Georgeff\Kernel\Module\ModuleRepositoryInterface $repository
     *
     * @return static
     */
    public function addRepository(ModuleRepositoryInterface $repository): static;

    /**
     * Get the container
     *
     * @return \Psr\Container\ContainerInterface
     */
    public function getContainer(): ContainerInterface;

    /**
     * Get the kernel start time (only available in debug mode)
     *
     * @return float
     */
    public function getStartTime(): float;

}
