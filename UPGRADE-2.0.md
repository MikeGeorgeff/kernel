# Upgrading from 1.x to 2.0

2.0 is a major release with several breaking changes. This guide walks through every change that requires action in consuming code, roughly in the order you'll hit them. See `CHANGELOG.md` for the full list of additions that aren't covered here (most 2.0 additions are opt-in and don't require any changes to upgrade).

## Requirements

- [ ] **PHP 8.4 or higher.** PHP 8.2 and 8.3 support was dropped; `composer.json` now requires `"php": "^8.4"`.
- [ ] **`georgeff/container` ^2.0.** 2.0 requires the matching major version of the container package.
- [ ] **`psr/event-dispatcher` can be removed** from your own `composer.json` if you only required it for this package — the kernel no longer depends on it (see PSR-14 removal below).

## 1. Environment: enum → interface

The `Environment` enum is gone, replaced by `Contract\EnvironmentInterface` with five built-in implementations.

- [ ] Replace every `Environment::Production` / `::Staging` / `::Development` / `::Testing` / `::Local` reference with the matching concrete class from `Environment\`:

  ```php
  // Before
  use Georgeff\Kernel\Environment;
  $kernel = new Kernel(Environment::Production);

  // After
  use Georgeff\Kernel\Environment\Production;
  $kernel = new Kernel(new Production());
  ```

- [ ] If you branched on the enum with `match`/`switch`, replace with `$env->is('production')` (string-based) or `$env instanceof Production` (class-based) — see `Contract\EnvironmentInterface::is(string ...$values): bool`.
- [ ] If you defined your own environment tier (e.g. a canary/blue-green case), this is no longer possible via enum cases — write your own class implementing `Contract\EnvironmentInterface` (or extending `Environment\AbstractEnvironment`) instead. This was the actual motivation for the change.
- [ ] Optional: if you're constructing environments from a string (e.g. `getenv('APP_ENV')`), consider `Support\EnvironmentResolver` instead of a hand-written `match` — register your environment classes once, then `$resolver->resolve($name)`. Entirely optional; nothing in the kernel requires it.
- [ ] Update any `ConfigurableModuleInterface::config(Environment $env)` implementations to `config(EnvironmentInterface $env)`.
- [ ] Update any `ModuleRepositoryInterface::modules(Environment $env)` implementations — see section 3 below, the interface itself is also gone.

## 2. Kernel constructor: second argument type

- [ ] If you pass a custom registrar as the second constructor argument, update its type. `ServiceRegistrar`/`ResolvingAwareServiceRegistrar` were collapsed into a single `Contract\ContainerBuilderInterface`:

  ```php
  // Before
  $kernel = new Kernel(Environment::Production, new MyServiceRegistrar());

  // After
  $kernel = new Kernel(new Production(), new MyContainerBuilder());
  ```

  If you only ever passed `null` (using the default registrar), no change needed beyond the environment argument above.

## 3. Modules: namespace moves + repository → aggregate

- [ ] Update imports for the four module interfaces — moved from `Module\` to `Contract\`:

  | Before | After |
  |---|---|
  | `Georgeff\Kernel\Module\ModuleInterface` | `Georgeff\Kernel\Contract\ModuleInterface` |
  | `Georgeff\Kernel\Module\ConfigurableModuleInterface` | `Georgeff\Kernel\Contract\ConfigurableModuleInterface` |
  | `Georgeff\Kernel\Module\BootableModuleInterface` | `Georgeff\Kernel\Contract\BootableModuleInterface` |

- [ ] If you use `ModuleRepositoryInterface`/`addRepository()`, both are gone. Replace with `Contract\AggregateModuleInterface extends ModuleInterface`:

  ```php
  // Before
  final class CoreModules implements ModuleRepositoryInterface
  {
      public function modules(Environment $env): array
      {
          return [new LoggingModule(), new DatabaseModule()];
      }
  }
  $kernel->addRepository(new CoreModules());

  // After
  final class CoreModules implements AggregateModuleInterface
  {
      public function register(KernelInterface $kernel): void {}

      public function modules(EnvironmentInterface $env): array
      {
          return [new LoggingModule(), new DatabaseModule()];
      }
  }
  $kernel->addModule(new CoreModules());
  ```

  Note `AggregateModuleInterface` extends `ModuleInterface`, so it needs a `register()` method too (can be a no-op if the aggregate itself has nothing to register), and it's added via the regular `addModule()` — there's no separate repository-registration call anymore. Aggregates can be nested (an aggregate can return further aggregates).

## 4. `kernel.*` container service IDs are gone

- [ ] If anything resolves `'kernel'` or `KernelInterface::class` from the container, stop — it's no longer registered. Whatever is wiring the service already has a direct `$kernel` reference (a module's `register(KernelInterface $kernel)` parameter, or your bootstrap script's own variable) — capture what you need from it directly instead of round-tripping through the container.
- [ ] If anything resolves `'kernel.environment'` or `'kernel.debug'`, replace with `$kernel->getEnvironment()` / `$kernel->isDebug()`, captured into your factory closure at definition time.
- [ ] If anything resolves `'kernel.tag.registry'`, replace with `DI\TagRegistryInterface::class`.
- [ ] If anything resolves `'kernel.config'`, replace with `Config\ConfigInterface::class` — and note the API is different (see section 8).

## 5. `addDefinition()` and `Kernel::tag()` removed

- [ ] Replace every `addDefinition()` call with the fluent `define()` builder:

  ```php
  // Before
  $kernel->addDefinition(Logger::class, fn() => new Logger(), shared: true, aliases: [LoggerInterface::class], tags: ['core']);

  // After
  $kernel->define(Logger::class, fn() => new Logger())
      ->share()
      ->alias(LoggerInterface::class)
      ->tag('core');
  ```

- [ ] Replace any standalone `$kernel->tag($id, $tags)` calls with `->tag()` chained directly onto the `define()` call that creates the definition. There is no replacement for tagging a definition after the fact (e.g. one registered by another module you don't own) — if you relied on that, the tags need to move to wherever the definition is actually created, or the owning module needs to add the tag itself.

## 6. `define()` now throws on redefinition

- [ ] Audit any code that calls `define()` (or the old `addDefinition()`, now migrated per above) twice for the same id expecting the second call to silently win — this now throws `DefinitionException` instead. Pick the right replacement for your actual intent:
  - Intentionally replacing a definition → `override($id, $factory)`
  - Only want to provide a fallback if nothing else defines the id → `defineFallback($id, $factory)`
  - Genuine accidental duplicate `define()` call → this is a bug the exception is now correctly catching; remove the duplicate.

## 7. PSR-14 event dispatching removed

- [ ] If you listen for `KernelBooted`, remove the listener and the `EventDispatcherInterface` container definition it depended on — the event is gone entirely, `boot()` no longer dispatches anything.
- [ ] Replace with `$kernel->onBooted(function () { ... })`, registered before `boot()` is called. This was already available in 1.3.0, so this is a drop-in replacement, not a new pattern to learn.

## 8. `kernel.config` → `Config\ConfigInterface`

- [ ] If you read config directly from the old flat `kernel.config` array, switch to the new immutable `Config` object:

  ```php
  // Before
  $config = $container->get('kernel.config');
  $port = $config['db']['port'] ?? 5432;

  // After
  $config = $container->get(ConfigInterface::class);
  $port = $config->branch('db')->get('port', 5432);
  ```

  `get(string $name, mixed $default = null)` checks `has()` first — an explicitly-stored `null` value is returned as `null`, not silently replaced by `$default`. `branch(string $name)` returns an empty `ConfigInterface` for a missing key rather than throwing; it only throws `ConfigException` if the value at that key exists but isn't a nested-config-shaped array (a scalar, a list, or numeric-string keys).

## 9. `DebuggableInterface` namespace move

- [ ] If any of your own services implement `DebuggableInterface`, update the import: `Debug\DebuggableInterface` → `Contract\DebuggableInterface`.
- [ ] If you have a test double that implements `KernelInterface` directly (not extending `Kernel`), it must now also implement `Contract\DebuggableInterface` (`getDebugInfo(): array`) — `KernelInterface` extends it as of 2.0.

## 10. Profiler: `-INF` sentinel → `null`

- [ ] If any code checks a profiler timing value against `-INF` (e.g. `Profiler\Profile::getStartTime()`, `getEndTime()`, `getOverallDuration()`, `getPhaseDuration()`, or `Kernel::getStartTime()`), switch that check to `null`. Return types widened from `float` to `?float` across the board — `-INF` is never returned anymore.

## 11. `getDebugInfo()` shape changes

If you parse `getDebugInfo()` output directly (dashboards, logging, tests) rather than just displaying it as-is, the following keys moved:

- [ ] `bootProfile` → `profiles.boot` (now nested under a `profiles` map, alongside the new `profiles.shutdown`)
- [ ] `services` → `components.service.resolution`
- [ ] New keys to be aware of, not migrations: `components.modules`, `components.service.resetter` — both present as soon as debug mode is enabled, even before `boot()` runs.

## 12. Raw SPL exceptions replaced with typed exceptions

- [ ] If you catch `\LogicException` or `\InvalidArgumentException` directly from this package anywhere, replace with the specific exception type (`ModuleException`, `ConfigException`, `EnvironmentException`, `DefinitionException`, `ServiceResetException`, `HookException`, or the general `KernelException`) or the shared `KernelExceptionInterface` marker if you want to catch any of them uniformly. Raw SPL exceptions are no longer thrown anywhere in this package.

## Not required, but worth adopting

These are new in 2.0 and don't require any change to upgrade — call them out separately so they don't get lost in the required-changes list above:

- **`resetShared()`** — if you run a long-lived worker/daemon process, resettable shared services (implement `Contract\ResettableInterface`) can be reset back to their original state between units of work without rebuilding the whole kernel.
- **`enableGc()`** — opt-in post-boot cleanup of boot-only working state, for the same long-running-process case.
- **`defineFallback()`** — register a definition that only applies if nothing else provides that id by boot time; useful for "sensible default unless overridden" wiring across independently-developed modules.
- **`getModules()`** — introspection: the class names of every added module, available immediately after `addModule()`.

## Verifying the upgrade

- [ ] `composer test` — full suite passes
- [ ] `composer analyze` — PHPStan clean at `level: max`
- [ ] Grep your own codebase for `Environment::`, `Module\`, `ModuleRepositoryInterface`, `addRepository`, `addDefinition`, `Debug\DebuggableInterface`, `kernel.config`, `kernel.environment`, `kernel.debug`, `'kernel'` container lookups, and `KernelBooted` — anything still matching needs one of the sections above.
