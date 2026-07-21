<?php

namespace Georgeff\Kernel;

use Psr\Container\ContainerInterface;
use Georgeff\Kernel\DI\DefinitionInterface;
use Georgeff\Kernel\Contract\ModuleInterface;
use Georgeff\Kernel\Contract\DebuggableInterface;
use Georgeff\Kernel\Contract\EnvironmentInterface;
use Georgeff\Kernel\Exception\KernelExceptionInterface;

interface KernelInterface extends DebuggableInterface
{
    /**
     * Boot the kernel
     *
     * @return void
     *
     * @throws KernelExceptionInterface
     */
    public function boot(): void;

    /**
     * Shutdown the kernel
     *
     * @return void
     *
     * @throws KernelExceptionInterface
     */
    public function shutdown(): void;

    /**
     * Indicates if the kernel is booting
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
     * Indicates if the kernel has been shutdown
     *
     * @return bool
     */
    public function isShutdown(): bool;

    /**
     * Get the kernel environment
     */
    public function getEnvironment(): EnvironmentInterface;

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
     *
     * @throws KernelExceptionInterface
     */
    public function onBooting(callable $callback): static;

    /**
     * Register a post-boot callback
     *
     * @param callable(KernelInterface): void $callback
     *
     * @return static
     *
     * @throws KernelExceptionInterface
     */
    public function onBooted(callable $callback): static;

    /**
     * Register a pre-shutdown callback
     *
     * @param callable(KernelInterface): void $callback
     *
     * @return static
     *
     * @throws KernelExceptionInterface
     */
    public function onShutdown(callable $callback): static;

    /**
     * Register a post shutdown callback
     *
     * @param callable(KernelInterface): void $callback
     *
     * @return static
     *
     * @throws KernelExceptionInterface
     */
    public function afterShutdown(callable $callback): static;

    /**
     * Register a pre-resolving callback
     *
     * @param callable(string): void $callback
     *
     * @return static
     *
     * @throws KernelExceptionInterface
     */
    public function onResolving(callable $callback): static;

    /**
     * Register a post-resolving callback
     *
     * @param callable(string, mixed): void $callback
     *
     * @return static
     *
     * @throws KernelExceptionInterface
     */
    public function onResolved(callable $callback): static;

    /**
     * Add a container definition using the fluent builder
     *
     * @param string                              $id
     * @param callable(ContainerInterface): mixed $factory
     *
     * @return DefinitionInterface
     *
     * @throws KernelExceptionInterface
     */
    public function define(string $id, callable $factory): DefinitionInterface;

    /**
     * Add a container definition if the definition ID does not already exist
     *
     * @param string                              $id
     * @param callable(ContainerInterface): mixed $factory
     *
     * @return DefinitionInterface
     *
     * @throws KernelExceptionInterface
     */
    public function defineFallback(string $id, callable $factory): DefinitionInterface;

    /**
     * Decorate a container definition
     *
     * @param string                                     $id
     * @param callable(mixed, ContainerInterface): mixed $decorator
     *
     * @return static
     *
     * @throws KernelExceptionInterface
     */
    public function decorate(string $id, callable $decorator): static;

    /**
     * Override an existing service definition
     *
     * @param string                              $id
     * @param callable(ContainerInterface): mixed $factory
     *
     * @return DefinitionInterface
     *
     * @throws KernelExceptionInterface
     */
    public function override(string $id, callable $factory, bool $preserve = false): DefinitionInterface;

    /**
     * Reset resolved shared services to their original state
     *
     * @return static
     *
     * @throws KernelExceptionInterface
     */
    public function resetServices(int $failureThreshold = 3): static;

    /**
     * Add a module to the kernel
     *
     * @param \Georgeff\Kernel\Contract\ModuleInterface $module
     *
     * @return static
     *
     * @throws KernelExceptionInterface
     */
    public function addModule(ModuleInterface $module): static;

    /**
     * Get a list of added modules
     *
     * @return list<class-string<ModuleInterface>>
     */
    public function getModules(): array;

    /**
     * Get the container
     *
     * @return \Psr\Container\ContainerInterface
     *
     * @throws KernelExceptionInterface
     */
    public function getContainer(): ContainerInterface;

    /**
     * Get the kernel start time (only available in debug mode)
     *
     * @return float
     */
    public function getStartTime(): float;

}
