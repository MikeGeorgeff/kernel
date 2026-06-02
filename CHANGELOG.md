# Changelog

All notable changes to `georgeff/kernel` are documented here.

---

## [1.5.0] — 2026-06-02

### Added
- `define(string $id, callable $factory): DefinitionInterface` on `Kernel` — returns a fluent definition builder as an alternative to `addDefinition()`; the returned `DefinitionInterface` exposes `share()`, `alias(string $alias)`, and `tag(string $tag)`, each returning the same instance for chaining
- `DefinitionInterface` — public contract for the fluent definition builder
- `Definition` — concrete implementation of `DefinitionInterface`

### Changed
- `addDefinition()` is now implemented in terms of `define()` internally; behavior is unchanged
- Reserved service guard for aliases moved from call time to the `serviceRegistration` boot phase; ID collisions are still caught immediately at `define()` / `addDefinition()` call time

---

## [1.4.0] — 2026-05-31

### Added
- `TagRegistryInterface` with `getTagged(string $tag): array` — resolves and returns all services registered under a given tag; available in the container as `kernel.tag.registry` (aliased to `TagRegistryInterface::class`)
- `tag(string $id, array $tags)` on `KernelInterface` and `Kernel` — tags an existing definition by service ID; throws if called after boot; duplicate tag/id pairs are silently ignored
- `addDefinition()` accepts a fifth `$tags` parameter; equivalent to calling `tag()` immediately after registration

---

## [1.3.0] — 2026-05-28

### Added
- `shutdown()` on `KernelInterface` and `Kernel` — runs the shutdown lifecycle; idempotent and a no-op if the kernel has not been booted
- `isShutdown()` on `KernelInterface` and `Kernel` — returns `true` after `shutdown()` has completed
- `onBooted()` — registers a post-boot callback that fires after boot completes and the `KernelBooted` event has been dispatched; must be registered before `boot()` is called
- `onShutdown()` — registers a pre-shutdown callback; fires before the kernel is marked as shut down; can be registered before or after boot, but not after shutdown
- `afterShutdown()` — registers a post-shutdown callback; fires after the kernel is marked as shut down; same registration window as `onShutdown()`

---

## [1.2.0] — 2026-05-27

### Added
- `ModuleInterface` with `register(KernelInterface): void` — the base contract all modules must implement
- `ConfigurableModuleInterface extends ModuleInterface` with `config(Environment): array` — declares config parameters merged into `kernel.config` during boot
- `BootableModuleInterface extends ModuleInterface` with `boot(ContainerInterface): void` — runs after the container is built
- `ModuleRepositoryInterface` with `modules(Environment): array` — for composing pre-defined module groups; the environment is passed for conditional inclusion
- `addModule()` on `KernelInterface` and `Kernel` — registers a single module; throws if called after boot has started or while modules are loading
- `addRepository()` on `KernelInterface` and `Kernel` — registers a module repository; same guards as `addModule()`
- `kernel.config` reserved service — the merged config array returned by all `ConfigurableModuleInterface` modules, available in the container after boot
- Three new boot phases: `moduleLoad` (repo flattening + config collection), `moduleRegistration` (module service registration), `moduleBoot` (post-container module boot)
- Profiler tracks the three new boot phases when debug mode is enabled
- `getDebugInfo()` includes module loader state (`loaded`, `registered`, `booted`, and the list of loaded module class names) when debug mode is enabled

### Changed
- `kernel.config` is now a reserved service ID; `addDefinition('kernel.config', ...)` throws `KernelException`

---

## [1.1.1] — 2026-02-13

### Fixed
- `Profiler::getDebugInfo()` now returns `json_encode`-safe values

---

## [1.1.0] — 2026-02-11

### Added
- `Profiler` with phase timing for `preBoot`, `serviceRegistration`, and `containerInit` boot phases
- `DebugContainer` decorator wrapping PSR-11 container, tracking per-service resolution times
- `ServiceResolutionProfile` and `ResolvedService` value objects
- `DebuggableInterface` for services to expose their own debug info
- `getDebugInfo()` on `Kernel` aggregating boot profile and container debug info
- `getStartTime()` returning kernel start time (debug mode only)

---

## [1.0.0] — 2026-02-09

### Added
- `KernelInterface` and `Kernel` implementation
- `Environment` enum (`production`, `staging`, `development`, `testing`)
- `ServiceRegistrar` interface and `DefaultServiceRegistrar` backed by `georgeff/container`
- `addDefinition()` for registering container service factories with shared and alias support
- `onBooting()` pre-boot callbacks
- `KernelBooted` event dispatched via PSR-14 after boot completes
- `RunnableKernelInterface` with `run(): int`
- Reserved kernel services: `kernel`, `kernel.environment`, `kernel.debug`
