# Changelog

All notable changes to `georgeff/kernel` are documented here.

---

## [2.0.0] — Unreleased

2.0 is a major release with several breaking changes. This entry will be finalized when 2.0.0 actually ships; it currently reflects everything merged to the `2.x` branch so far.

### Added
- `Contract\EnvironmentInterface` (`getValue(): string`, `is(string ...$values): bool`) — replaces the `Environment` enum; consumers can now define their own environments (e.g. a canary/blue-green tier) instead of being limited to fixed enum cases
- `Environment\AbstractEnvironment` (implements `is()`) plus five concrete leaf classes — `Environment\Production`, `Environment\Staging`, `Environment\Development`, `Environment\Testing`, `Environment\Local` — replacing the old enum cases with the same string values
- `Support\EnvironmentResolver` — name-string → `class-string<EnvironmentInterface>` registry (`register()`, `resolve()`, `registered()`); an optional convenience, not required to construct a `Kernel`
- `Contract\AggregateModuleInterface extends ModuleInterface` with `modules(EnvironmentInterface $env): ModuleInterface[]` — a module can now compose and cascade-load other modules directly, replacing `ModuleRepositoryInterface`; recursive (an aggregate can return further aggregates), and reuses the existing module-dedup guard for cycle safety
- `KernelInterface::getModules(): list<class-string<ModuleInterface>>` — introspection; the class names of every added module, available immediately after `addModule()`, before `boot()`
- `Contract\ContainerBuilderInterface` (`register()`, `onResolving()`, `onResolved()`, `getContainer()`) — collapses the old `ServiceRegistrar`/`ResolvingAwareServiceRegistrar` pair into one interface; `DI\ContainerBuilder` (`@internal`) is the default implementation
- `Kernel::defineFallback(string $id, callable $factory): DefinitionInterface` — registers a definition that's only used if nothing else defines that id by boot time; resolved in a new `serviceFallbacks` boot phase (after `moduleRegistration`, before `serviceOverrides`/`serviceDecoration`), so it's independent of module registration order either way. Multiple `defineFallback()` calls for the same id from unrelated modules do not conflict — none of them has an opinion about which implementation wins, only that something does, so the last one registered silently wins
- `Config\ConfigInterface`/`Config\Config` — immutable module config, replacing the flat `kernel.config` array; `has()`, `get(string $name, mixed $default = null)` (checks `has()` first, so an explicitly-stored `null` is returned rather than falling back to `$default`), `isEmpty()`, and `branch(string $name): ConfigInterface` for fluent nested-array traversal (`$config->branch('db')->get('port')`) — returns an empty `ConfigInterface` for a missing key, throws `ConfigException` for anything else that isn't a non-numeric-string-keyed array (a scalar, a list, or a value with numeric-looking string keys), so a stray list value can't silently be treated as a nested config
- `Contract\ResettableInterface` (`reset(): void`) and `Kernel::resetServices(int $failureThreshold = 3): static` — resets resolved shared services back to their original state; auto-detected via a post-resolution hook, no manual tagging required; intended for long-running processes (workers/daemons) wanting a clean slate between units of work
- `Contract\ThresholdAwareResettableInterface extends ResettableInterface` (`getFailureThreshold(): int`) — lets an individual service override the default failure threshold passed to `resetServices()`
- `ServiceResetException` — thrown once a service's `reset()` failure count reaches its threshold; failures are tracked per container id (not per class, so the same class backing two different ids is tracked independently) and accumulate across calls to `resetServices()` until a successful reset clears that service's history
- `service.resetter` key in `getDebugInfo()` (failure counts + logged exception messages per service id)
- `Exception\KernelExceptionInterface extends \Throwable` — marker interface implemented by every exception this package throws, so callers can catch one type regardless of which specific exception was thrown
- `Exception\ThrowHelpers` trait (`instance()`, `throw()`, `throwIf()`, `throwIfNot()`) — shared by every concrete exception class; `throw()`/`throwIf()`/`throwIfNot()` now all accept an optional `$previous` throwable
- `ModuleException`, `ConfigException`, `EnvironmentException`, `DefinitionException`, `ServiceResetException` — dedicated exception types (all `KernelExceptionInterface`, all `\RuntimeException`) for their respective areas, replacing generic `KernelException`/raw SPL exceptions in those spots
- `ModuleException::throwOnRegistrationError()` / `throwOnBootError()` — wrap an unexpected throwable from a module's `register()`/`boot()` with the original preserved as `$previous`; a thrown `KernelExceptionInterface` or `ContainerExceptionInterface` passes through unwrapped instead of being re-wrapped
- `HookException` — thrown when a lifecycle callback (`onBooting`/`onBooted`/`onShutdown`/`afterShutdown`) fails; `throwOnCallbackError()` wraps a single failure, `throwOnCallbackErrors()` aggregates multiple into one message (used by the shutdown hooks — see the `Changed` entry below)
- `Kernel::enableGc(): static` — opt-in post-boot cleanup of `DefinitionRepository`'s, `ModuleLoader`'s, and `HookRepository`'s boot-only working state (`onBooting`/`onBooted` callbacks, consumed once boot completes), for long-running processes that keep a `Kernel` instance alive after `boot()` returns. Not automatic — not every environment needs it — so it must be called before `boot()`, throwing `KernelException` every time it's called afterward, even if it was already successfully enabled beforehand. Can be called from anywhere with a reference to the kernel, including from a module's own `register()`, so a module can opt the whole kernel into cleanup on its own authority without the top-level bootstrap needing to know or coordinate; an internal flag makes repeated calls (e.g. two modules both opting in) idempotent, so only one cleanup callback is ever actually registered regardless of how many callers opt in. `onShutdown`/`afterShutdown` callbacks are deliberately left uncleared regardless, since they haven't run yet at that point
- `shutdown()` now does real cleanup work, not just callback invocation: the built container is released, `DI\ServiceResetter`'s tracked services and failure history are cleared, and the whole thing is profiled (in debug mode) under `getDebugInfo()['profiles']['shutdown']`, with `shuttingDown` and `afterShutdown` phases and its own overall `duration` once shutdown completes. If an `onShutdown` callback fails, none of this cleanup runs and `isShutdown()` stays `false` — shutdown either completes in full or is treated as not having happened, consistent with the callback-failure handling described below
- `Kernel::getContainer()` and `Kernel::resetServices()` now throw `KernelException` if called after `shutdown()`, in addition to their existing before-boot guards
- `Profiler\Profiler` — aggregation layer behind `Kernel::getDebugInfo()`. Tracks any number of named `Profiler\Profile` instances (`initProfile()`, `hasProfile()`, `getProfile()`, `removeProfile()`) under a `profiles` key, and lets any `DebuggableInterface` service `register()` itself under a name to be merged into the output under a separate `components` key (kept apart from `profiles` so a registered component name can never collide with a profile name), replacing the kernel's previous hand-assembled `getDebugInfo()` array
- `Profiler\Profile::getStartTime(): float` (this class was `Debug\Profiler` — see the rename below) — returns the value passed to `start()`, or `-INF` if `start()` hasn't been called
- `ProfilerException` — thrown by `Profiler::getProfile()` when asked for a profile name that hasn't been initialized

### Changed
- **Breaking:** `KernelInterface::getEnvironment()` return type changed from `string` to `EnvironmentInterface`
- **Breaking:** `ConfigurableModuleInterface::config()` and `AggregateModuleInterface::modules()` now receive `EnvironmentInterface $env` instead of the old `Environment` enum
- **Breaking:** `KernelInterface extends Contract\DebuggableInterface` — `getDebugInfo()` is now part of the interface contract, not just implemented by the concrete `Kernel` class; any direct `KernelInterface` implementation (e.g. a test double) must now also implement `DebuggableInterface`
- **Breaking:** `define()` (via `DefinitionRepository::add()`) now throws `DefinitionException` when redefining an id that's already defined, instead of silently letting the later call win. This was only ever silent because `override()` didn't exist yet when `define()` was first written — with `override()` (intentional replacement) and `defineFallback()` (define-only-if-missing) now covering those cases explicitly, `define()` no longer needs to double as a silent-overwrite mechanism and can fail loud on accidental id collisions instead
- **Breaking:** the `kernel.*` reserved-service guard (`$reservedServices`, checked inside `define()`/`decorate()`/`override()`) is gone. `kernel`/`KernelInterface::class`, `kernel.environment`, and `kernel.debug` are no longer registered in the container at all — anything wiring a service already holds a direct reference to the kernel (via a module's `register(KernelInterface $kernel)` or the bootstrap script's own variable) and can capture `$kernel->isDebug()`/`$kernel->getEnvironment()` directly instead of resolving it from the container. `kernel.tag.registry`/`kernel.config` are replaced by `DI\TagRegistryInterface::class`/`Config\ConfigInterface::class`, registered directly by the kernel after all other definitions during the `serviceRegistration` phase instead of being protected by a hard guard
- Module interfaces moved to the `Contract\` namespace: `Contract\ModuleInterface`, `Contract\ConfigurableModuleInterface`, `Contract\BootableModuleInterface`, `Contract\AggregateModuleInterface`
- `Contract\RunnableKernelInterface` moved to the `Contract\` namespace and now extends `KernelInterface` directly
- **Breaking:** `DebuggableInterface` moved from `Debug\DebuggableInterface` to `Contract\DebuggableInterface`, alongside the other extension-point interfaces
- `Debug\Profiler` (single-timer phase profiling) renamed to `Profiler\Profile`; behavior unchanged, only the name and namespace moved to make room for the new `Profiler\Profiler` aggregation layer above
- `ModuleLoader`'s internal lifecycle guards (add-after-load, register-before-load, boot-before-register) now throw `ModuleException` instead of raw `\LogicException`
- `KernelException::throw()`/`throwIf()`/`throwIfNot()` gained an optional `$previous` parameter (`throw()` previously took no `$previous` at all)
- Lifecycle callback failure handling: a failing `onBooting`/`onBooted` callback still aborts immediately (matching boot's sequential, order-dependent nature), but a raw non-`KernelExceptionInterface`/`ContainerExceptionInterface` throwable is now wrapped in `HookException` rather than propagating unwrapped. `onShutdown`/`afterShutdown` callbacks now run to completion even if an earlier one throws — all failures are collected and reported together in a single aggregated `HookException` once every callback has had a chance to run, instead of the first failure aborting the rest of shutdown
- `KernelInterface::boot()`/`shutdown()` now document `@throws KernelExceptionInterface`
- `getDebugInfo()`'s profile data moved from a flat `bootProfile` key to `profiles.boot`, nested under a `profiles` map alongside the new `profiles.shutdown`; service resolution data moved from `services` to `components.service.resolution`. `components.modules` and `components.service.resetter` are registered against the profiler at kernel construction time when debug mode is enabled, so both are present in `getDebugInfo()` immediately — even before `boot()` is called — rather than only appearing once boot/resolution has actually happened
- `Kernel::getStartTime()` now checks `hasProfile('boot')` before calling `getProfile('boot')`, so it safely returns `-INF` when debug mode is enabled but `boot()` hasn't run yet, instead of throwing `ProfilerException`
- `Kernel::$profiler` uses PHP 8.4 asymmetric visibility (`protected private(set)`): readable by subclasses, but only `Kernel` itself can assign to it
- Dropped PHP 8.2 and 8.3 support — now requires `php: ^8.4`

### Removed
- **Breaking:** `Environment` enum
- **Breaking:** `addDefinition()` and standalone `Kernel::tag()` — `define(...)->share()`/`->alias()`/`->tag()` is now the only path for registering a service definition
- **Breaking:** `ModuleRepositoryInterface` and `addRepository()` — replaced by `AggregateModuleInterface`, which lets a module compose other modules directly instead of requiring a separate top-level repository-registration step
- **Breaking:** `kernel`, `kernel.environment`, `kernel.debug`, `kernel.config` container service IDs — see the reserved-service change above
- **Breaking:** PSR-14 event dispatching — the `KernelBooted` event and the `psr/event-dispatcher` dependency are gone; `boot()` no longer looks for an `EventDispatcherInterface` in the container or dispatches anything after boot completes. Use `onBooted()` instead
- Raw SPL exceptions (`\LogicException`, `\InvalidArgumentException`) are no longer thrown directly anywhere in this package

---

## [1.10.1] — 2026-07-18

### Fixed
- `Definition::alias()` / `Definition::tag()` — repeated identical calls no longer accumulate duplicate entries in `getAliases()`/`getTags()`; deduped at the source instead of relying on incidental protection elsewhere (the now-redundant duplicate guard in `Kernel::boot()`'s tag registration was removed alongside this)
- `Profiler::getOverallDuration()` — replaced a loose falsy check (`!$this->start`/`!$this->end`) with an explicit `null` check; the old check could misread a legitimate `0.0` timestamp as unset
- `Kernel::boot()` — now throws `KernelException` if called reentrantly while already booting, matching the guard `addModule()`/`addRepository()` already had. Previously, a module calling `$kernel->boot()` from its own `register()` would recurse into `ModuleLoader::register()` uncontrolled instead of failing fast
- `Kernel::profile()` — boot phase timing now wrapped in `try`/`finally`, so a phase's end time is always recorded even if its callback throws; previously an exception mid-phase (e.g. a module's `register()` failing) left that phase's duration permanently unrecorded (`null` in `getDebugInfo()`), including in the failure case where that data is most useful for diagnosis

---

## [1.10.0] — 2026-07-13

### Added
- `override(string $id, callable $factory, bool $preserve = false): DefinitionInterface` — replaces an existing service definition outright. Applied in a dedicated `serviceOverrides` boot phase that runs after all modules have registered but before decoration, so an override always wins even when registered before the module defining the same service. Throws `KernelException` if called after boot, for a reserved service ID, or (at boot time) if the target definition does not exist. Unlike `decorate()`, does not inherit the original definition's shared flag, aliases, or tags by default; pass `preserve: true` to copy them onto the override.

---

## [1.9.0] — 2026-06-19

### Added
- `ResolvingAwareServiceRegistrar` interface extending `ServiceRegistrar` with `afterResolved(callable $callback): void`; registrars that implement it receive a post-resolution hook from the kernel in debug mode
- `DefaultServiceRegistrar` now implements `ResolvingAwareServiceRegistrar`; delegates to `georgeff/container`'s `afterResolved()` hook (requires `georgeff/container ^1.1`)

### Changed
- Debug resolution tracking no longer wraps the container in a `DebugContainer`; the kernel now registers an `afterResolved` hook via `ResolvingAwareServiceRegistrar` during the `containerInit` boot phase when debug mode is enabled; the `services` key in `getDebugInfo()` is only present when the registrar implements `ResolvingAwareServiceRegistrar`
- `ServiceResolutionProfile` renamed to `ServiceResolution` (`@internal`); `resolve()` signature changed from `(string $id, float $resolutionTime)` to `(string $id, mixed $resolved)` — now stores the resolved instance rather than timing
- `ResolvedService` (`@internal`) constructor now requires the resolved instance as a second argument; resolution time tracking (`addResolutionTime()`, `getResolutionTime()`) removed; `getDebugInfo()` now includes a `debugInfo` key when the resolved instance implements `DebuggableInterface`
- `getDebugInfo()` key for service resolution data changed from `serviceResolutionProfile` to `services`

### Removed
- `DebugContainer` removed; replaced by the `ResolvingAwareServiceRegistrar` hook mechanism

---

## [1.8.1] — 2026-06-19

### Changed
- `DebugContainer`, `ResolvedService`, `ServiceResolutionProfile`, `Profiler`, `ModuleLoader`, `Definition`, and `DefaultServiceRegistrar` are now marked `@internal`; these classes have never been part of the public API and will be restructured in 1.9

---

## [1.8.0] — 2026-06-18

### Changed
- `decorate()` now supports multiple decorators on the same service ID; the previously thrown `KernelException` on re-decoration has been removed; decorators are chained innermost-first (first registered wraps the original, each subsequent decorator wraps the previous result)

---

## [1.7.0] — 2026-06-15

### Added
- `Environment::Local` — new enum case (`'local'`) for local development machines; `Environment::Development` remains the remote dev/integration tier; additive, no breaking change
- `KernelException::throw(string $message): never` — static helper that always throws a `KernelException`
- `KernelException::throwIf(bool $condition, string $message): void` — throws when `$condition` is `true`
- `KernelException::throwIfNot(bool $condition, string $message): void` — throws when `$condition` is `false`
- `Support\Env::get(string $name, mixed $default = null): mixed` — wraps `getenv()` with type coercion; coerces boolean variants (`true`/`false` and their `(true)`/`TRUE` forms), null variants, and JSON objects/arrays to native types; numeric strings are intentionally left as strings

### Changed
- Lifecycle hook callbacks (`onBooting`, `onBooted`, `onShutdown`, `afterShutdown`) are now stored in an internal `Hook\HookRepository` instead of directly on kernel properties; no public API change

---

## [1.6.0] — 2026-06-03

### Added
- `decorate(string $id, callable $decorator): static` on `KernelInterface` and `Kernel` — wraps an existing service definition with a decorator; the decorator callable receives the resolved inner service and the container `(mixed $inner, ContainerInterface $container): mixed`; the decorated service inherits the original's shared flag, aliases, and tags; throws if called after boot or for a reserved service ID
- `isBooting(): bool` on `KernelInterface` and `Kernel` — returns `true` while `boot()` is in progress, before `isBooted()` becomes `true`
- `serviceDecoration` boot phase between `moduleRegistration` and `serviceRegistration` — all pending decorators are applied after modules have registered their definitions so module load order does not affect decoration

### Changed
- `addModule()` and `addRepository()` now throw with a distinct message when called while the kernel is booting (`isBooting()` is `true`), replacing the previous opaque "modules are locked" error

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
