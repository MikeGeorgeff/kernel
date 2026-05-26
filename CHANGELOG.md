# Changelog

All notable changes to `georgeff/kernel` are documented here.

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
