# Changelog

All notable changes to `georgeff/kernel` are documented here.

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
