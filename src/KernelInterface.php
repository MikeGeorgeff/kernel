<?php

namespace Georgeff\Kernel;

use Psr\Container\ContainerInterface;
use Georgeff\Kernel\DI\DefinitionInterface;
use Georgeff\Kernel\Contract\ModuleInterface;
use Georgeff\Kernel\Contract\DebuggableInterface;
use Georgeff\Kernel\Contract\EnvironmentInterface;
use Georgeff\Kernel\Exception\KernelExceptionInterface;

/**
 * Central point of contact for the application: register modules, definitions, and lifecycle hooks.
 */
interface KernelInterface extends DebuggableInterface
{
    /**
     * Boot the kernel
     *
     * @throws KernelExceptionInterface
     */
    public function boot(): void;

    /**
     * Shut down the kernel
     *
     * @throws KernelExceptionInterface
     */
    public function shutdown(): void;

    /**
     * Whether the kernel is currently booting
     */
    public function isBooting(): bool;

    /**
     * Whether the kernel has finished booting
     */
    public function isBooted(): bool;

    /**
     * Whether the kernel has been shut down
     */
    public function isShutdown(): bool;

    /**
     * The environment the kernel was constructed with
     */
    public function getEnvironment(): EnvironmentInterface;

    /**
     * Whether debug mode is enabled
     */
    public function isDebug(): bool;

    /**
     * Register a callback to run before boot begins
     *
     * @param callable(KernelInterface): void $callback
     *
     * @throws KernelExceptionInterface
     */
    public function onBooting(callable $callback): static;

    /**
     * Register a callback to run after boot completes
     *
     * @param callable(KernelInterface): void $callback
     *
     * @throws KernelExceptionInterface
     */
    public function onBooted(callable $callback): static;

    /**
     * Register a callback to run before shutdown begins
     *
     * @param callable(KernelInterface): void $callback
     *
     * @throws KernelExceptionInterface
     */
    public function onShutdown(callable $callback): static;

    /**
     * Register a callback to run after shutdown completes
     *
     * @param callable(KernelInterface): void $callback
     *
     * @throws KernelExceptionInterface
     */
    public function afterShutdown(callable $callback): static;

    /**
     * Register a callback invoked before a service is resolved from the container
     *
     * @param callable(string): void $callback
     *
     * @throws KernelExceptionInterface
     */
    public function onResolving(callable $callback): static;

    /**
     * Register a callback invoked after a service is resolved from the container
     *
     * @param callable(string, mixed): void $callback
     *
     * @throws KernelExceptionInterface
     */
    public function onResolved(callable $callback): static;

    /**
     * Opt in to releasing internal bookkeeping state once boot completes, for long-running processes that don't need it afterward
     *
     * @throws KernelExceptionInterface
     */
    public function enableGc(): static;

    /**
     * Register a new container definition
     *
     * @param callable(ContainerInterface): mixed $factory
     *
     * @throws KernelExceptionInterface
     */
    public function define(string $id, callable $factory): DefinitionInterface;

    /**
     * Register a definition that is only used if nothing else defines the same id by the time the kernel boots
     *
     * @param callable(ContainerInterface): mixed $factory
     *
     * @throws KernelExceptionInterface
     */
    public function defineFallback(string $id, callable $factory): DefinitionInterface;

    /**
     * Wrap an existing definition's factory. The original's shared flag, aliases, and tags are preserved
     *
     * @param callable(mixed, ContainerInterface): mixed $decorator
     *
     * @throws KernelExceptionInterface
     */
    public function decorate(string $id, callable $decorator): static;

    /**
     * Replace an existing definition's factory outright. Pass $preserve to also keep the original's shared flag, aliases, and tags
     *
     * @param callable(ContainerInterface): mixed $factory
     *
     * @throws KernelExceptionInterface
     */
    public function override(string $id, callable $factory, bool $preserve = false): DefinitionInterface;

    /**
     * Reset every shared, resettable service back to its original state, or only those tagged
     * with the given tags if any are provided. Stops after $failureThreshold consecutive failures
     *
     * @throws KernelExceptionInterface
     */
    public function resetShared(int $failureThreshold = 3, string ...$tags): static;

    /**
     * Add a module to the kernel
     *
     * @throws KernelExceptionInterface
     */
    public function addModule(ModuleInterface $module): static;

    /**
     * The class of every module that has been added to the kernel
     *
     * @return list<class-string<ModuleInterface>>
     */
    public function getModules(): array;

    /**
     * The built container
     *
     * @throws KernelExceptionInterface
     */
    public function getContainer(): ContainerInterface;

    /**
     * The kernel's boot start time, available only in debug mode
     */
    public function getStartTime(): ?float;
}
