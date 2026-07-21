# Kernel

A lightweight application kernel with service container bootstrapping, a module system, and lifecycle callbacks.

## Installation

```bash
composer require georgeff/kernel
```

## Usage

### Basic Bootstrapping

```php
use Georgeff\Kernel\Environment\Production;
use Georgeff\Kernel\Kernel;

$kernel = new Kernel(new Production());

$kernel->define('logger', fn() => new FileLogger('/var/log/app.log'))->share();
$kernel->define('mailer', fn() => new SmtpMailer('localhost'))->share();

$kernel->boot();

$container = $kernel->getContainer();
$logger = $container->get('logger');
```

### Environments

`EnvironmentInterface` (`getValue(): string`, `is(string ...$values): bool`) replaces a fixed enum, so consumers can define their own environments. Five concrete environments ship with the package:

- `Environment\Production`
- `Environment\Staging`
- `Environment\Development`
- `Environment\Testing`
- `Environment\Local`

`Local` is for local development machines. `Development` is the remote dev/integration tier.

```php
use Georgeff\Kernel\Environment\Local;
use Georgeff\Kernel\Kernel;

$kernel = new Kernel(new Local(), debug: true);

$kernel->getEnvironment()->getValue(); // 'local'
$kernel->getEnvironment()->is('local'); // true
$kernel->isDebug();                     // true
```

To add your own environment, implement `EnvironmentInterface` directly or extend `AbstractEnvironment` (which already implements `is()`):

```php
use Georgeff\Kernel\Environment\AbstractEnvironment;

final class Canary extends AbstractEnvironment
{
    public function getValue(): string
    {
        return 'canary';
    }
}
```

#### EnvironmentResolver

`Support\EnvironmentResolver` is an optional convenience for resolving an environment from a string (e.g. an `APP_ENV` value) without hand-writing a switch statement. It is not required — nothing in the kernel depends on it, and `new Kernel(new Production())` works with zero awareness it exists.

```php
use Georgeff\Kernel\Support\EnvironmentResolver;
use Georgeff\Kernel\Support\Env;

$resolver = new EnvironmentResolver();
$resolver->register('canary', Canary::class);

$kernel = new Kernel($resolver->resolve(Env::get('APP_ENV', 'production')));
```

`register()` throws `EnvironmentException` if the class doesn't exist or doesn't implement `EnvironmentInterface`. `resolve()` throws `EnvironmentException` for an unregistered name.

### Service Definitions

`define()` registers a service definition and returns a `DefinitionInterface` for fluent configuration:

```php
$kernel->define('db.connection', fn() => new PdoConnection($dsn, $user, $pass))
    ->share()
    ->alias(ConnectionInterface::class)
    ->tag('db.connections');
```

| Method | Description |
|---|---|
| `share()` | Register the service as a singleton |
| `alias(string $alias)` | Add a container alias |
| `tag(string $tag)` | Add a tag |

All three return the same definition instance, so they can be chained in any order.

`define()` throws `DefinitionException` if the id is already defined — each id can only be claimed once. Use `override()` to intentionally replace an existing definition, or `defineFallback()` to register a definition that's only used if nothing else claims the id.

### Service Definition Fallbacks

`defineFallback()` registers a definition that's only used if nothing else defines that id by the time the kernel boots — useful for a module that wants to provide a sensible default without forcing consumers to configure it, or without conflicting with a "real" definition another module provides:

```php
$kernel->defineFallback('db.connection', fn() => new PdoConnection('sqlite::memory:'))->share();

// Some other module (or the application itself) defines the real thing:
$kernel->define('db.connection', fn() => new PdoConnection($dsn, $user, $pass))->share();

$kernel->boot();
// The real definition wins — the fallback is never used, regardless of which was registered first.
```

If nothing else defines the id, the fallback is used instead:

```php
$kernel->defineFallback('db.connection', fn() => new PdoConnection('sqlite::memory:'))->share();

$kernel->boot();
// PdoConnection('sqlite::memory:') — nothing else claimed 'db.connection'.
```

Multiple modules can register a fallback for the same id without conflict. Unlike `define()`, `defineFallback()` never throws for a duplicate id — none of the callers has an opinion about which implementation wins, only that *something* ends up satisfying the id, so the last one registered silently wins if more than one is present and nothing else defines the id outright.

`defineFallback()` throws `KernelException` if called after boot.

### Definition Tags

Tags group service definitions under a shared label so they can be collected and resolved together:

```php
$kernel->define(FirstMiddleware::class, fn() => new FirstMiddleware())->share()->tag('http.middleware');
$kernel->define(SecondMiddleware::class, fn() => new SecondMiddleware())->share()->tag('http.middleware');
```

Retrieve all services for a tag via `TagRegistryInterface` after boot:

```php
use Georgeff\Kernel\DI\TagRegistryInterface;

$kernel->boot();

$registry   = $kernel->getContainer()->get(TagRegistryInterface::class);
$middleware = $registry->getTagged('http.middleware');
// [FirstMiddleware, SecondMiddleware] — resolved in registration order
```

Tagging the same id with the same tag more than once is idempotent — `tag()` only adds the tag if it isn't already present.

### Service Decoration

`decorate()` wraps an existing service definition with a decorator. The decorator callable receives the resolved inner service and the container:

```php
$kernel->define(LoggerInterface::class, fn() => new FileLogger('/var/log/app.log'))->share();

$kernel->decorate(
    LoggerInterface::class,
    fn(LoggerInterface $inner, ContainerInterface $c) => new TimestampLogger($inner),
);

$kernel->boot();

$logger = $kernel->getContainer()->get(LoggerInterface::class);
// TimestampLogger wrapping FileLogger
```

The decorated service automatically inherits the original's shared flag, aliases, and tags — existing consumers resolve the decorated version transparently. Multiple decorators on the same id stack — the first registered wraps the original, each subsequent decorator wraps the previous result.

`decorate()` can be called from a module's `register()` method to decorate a service contributed by another module. Because decoration is applied after all modules have registered, load order does not matter:

```php
final class LoggingModule implements ModuleInterface
{
    public function register(KernelInterface $kernel): void
    {
        $kernel->decorate(
            CacheInterface::class,
            fn(CacheInterface $inner, ContainerInterface $c) => new LoggingCache(
                $inner,
                $c->get(LoggerInterface::class),
            ),
        );
    }
}
```

`decorate()` throws `KernelException` if called after boot. A `DefinitionException` is thrown at boot time if the target definition does not exist.

### Service Overrides

`override()` replaces an existing service definition outright — unlike `decorate()`, which wraps the original, `override()` swaps it for a completely different implementation. This is the tool for substituting fakes or test doubles for real services, especially ones registered by a module:

```php
$kernel->define(QueueInterface::class, fn() => new RabbitMQQueue($config))->share();

$kernel->override(QueueInterface::class, fn() => new InMemoryQueue());

$kernel->boot();

$queue = $kernel->getContainer()->get(QueueInterface::class);
// InMemoryQueue
```

Because the override is applied in its own boot phase — after all modules have registered but before decoration and container registration — it always wins, even when it targets a service a module hasn't registered yet at the point `override()` is called:

```php
$kernel->override(QueueInterface::class, fn() => new InMemoryQueue());

$kernel->addModule(new QueueModule()); // registers the real QueueInterface

$kernel->boot();
// InMemoryQueue wins - the override is applied after QueueModule::register() runs
```

Unlike `decorate()`, `override()` does not inherit the original definition's shared flag, aliases, or tags by default — the replacement may have entirely different requirements than what it's replacing. Configure the replacement explicitly via the returned `DefinitionInterface`:

```php
$kernel->override(QueueInterface::class, fn() => new InMemoryQueue())->share();
```

Pass `preserve: true` to copy the original's shared flag, aliases, and tags onto the override instead:

```php
$kernel->override(QueueInterface::class, fn() => new InMemoryQueue(), preserve: true);
// InMemoryQueue is shared, aliased, and tagged exactly like the original QueueInterface registration
```

`override()` throws `KernelException` if called after boot. A `DefinitionException` is thrown at boot time if the target definition does not exist — `override()` can only replace something, not create it. An `override()` can target an id that only exists because a `defineFallback()` backfilled it, since fallbacks resolve before overrides do.

### Modules

Modules are self-contained units that contribute service definitions, configuration, and boot logic to the kernel.

#### Defining a Module

Every module implements `ModuleInterface` with a single `register()` method:

```php
use Georgeff\Kernel\KernelInterface;
use Georgeff\Kernel\Contract\ModuleInterface;

final class DatabaseModule implements ModuleInterface
{
    public function register(KernelInterface $kernel): void
    {
        $kernel->define('db.connection', fn() => new PdoConnection(getenv('DB_DSN')))
            ->share()
            ->alias(ConnectionInterface::class);
    }
}
```

#### Configuration

Modules that need to declare configuration implement `ConfigurableModuleInterface`. The returned array is merged from every configurable module and made available as `Config\ConfigInterface` in the container after boot:

```php
use Georgeff\Kernel\Config\ConfigInterface;
use Georgeff\Kernel\Contract\ConfigurableModuleInterface;
use Georgeff\Kernel\Contract\EnvironmentInterface;
use Georgeff\Kernel\KernelInterface;

final class DatabaseModule implements ConfigurableModuleInterface
{
    public function register(KernelInterface $kernel): void
    {
        $kernel->define('db.connection', fn(ContainerInterface $c) => new PdoConnection(
            $c->get(ConfigInterface::class)->branch('db')->get('dsn'),
        ))->share();
    }

    public function config(EnvironmentInterface $env): array
    {
        return [
            'db' => [
                'dsn'  => getenv('DB_DSN') ?: 'sqlite::memory:',
                'host' => getenv('DB_HOST') ?: 'localhost',
            ],
        ];
    }
}
```

The `$env` parameter is available for structural differences — for example, swapping a real driver for an in-memory one in testing:

```php
public function config(EnvironmentInterface $env): array
{
    return [
        'db' => [
            'dsn' => $env->is('testing') ? 'sqlite::memory:' : getenv('DB_DSN'),
        ],
    ];
}
```

Config from multiple modules is merged in registration order. Later definitions overwrite earlier ones for the same top-level key.

`Env::get()` is available for reading environment variables with automatic type coercion — useful in `config()` implementations:

```php
use Georgeff\Kernel\Support\Env;

public function config(EnvironmentInterface $env): array
{
    return [
        'db' => [
            'dsn'  => Env::get('DB_DSN', 'sqlite::memory:'),
            'port' => Env::get('DB_PORT', '3306'),
            'log'  => Env::get('DB_LOG', false),
        ],
    ];
}
```

Coercion rules:

| Value | Result |
|---|---|
| `'true'`, `'TRUE'`, `'(true)'` | `true` |
| `'false'`, `'FALSE'`, `'(false)'` | `false` |
| `'null'`, `'NULL'`, `'(null)'` | `null` |
| Valid JSON object or array | `array` |
| Anything else | raw `string` |

Numeric strings are intentionally left as strings — port numbers and similar values are most useful as strings.

#### Reading Config

`Config\ConfigInterface` (resolved from the container) exposes `has()`, `get()`, `isEmpty()`, and `branch()` for fluent traversal into nested array config values:

```php
use Georgeff\Kernel\Config\ConfigInterface;

$config = $kernel->getContainer()->get(ConfigInterface::class);

$config->has('db');                    // true
$config->branch('db')->get('port');    // 5432
$config->branch('missing')->isEmpty(); // true — a missing key produces an empty (not null) branch
```

`get(string $name, mixed $default = null)` checks `has()` first, so an explicitly-stored `null` value is returned as-is rather than falling back to `$default`. `branch()` throws `ConfigException` if the value at that key exists but isn't an array with non-numeric string keys — a scalar or a list value can't silently be treated as a nested config, so a config-shape mistake fails loud instead of quietly returning nothing further down the chain.

#### Module Boot

Modules that need access to the built container implement `BootableModuleInterface`. `boot()` is called after the container is fully initialized:

```php
use Georgeff\Kernel\KernelInterface;
use Georgeff\Kernel\Contract\BootableModuleInterface;
use Psr\Container\ContainerInterface;

final class MigrationModule implements BootableModuleInterface
{
    public function register(KernelInterface $kernel): void { /* ... */ }

    public function boot(ContainerInterface $container): void
    {
        $container->get(Migrator::class)->run();
    }
}
```

Because the container is already built when `boot()` is called, new service definitions cannot be added here — use `register()` for that.

If a module's `register()` or `boot()` throws anything other than a `KernelExceptionInterface` or a PSR-11 `ContainerExceptionInterface`, it's wrapped in a `ModuleException` with the original preserved as the previous exception, and identifies which module and which phase failed.

#### Registering Modules

Register modules on the kernel before booting:

```php
$kernel = new Kernel(new Production());

$kernel
    ->addModule(new DatabaseModule())
    ->addModule(new CacheModule())
    ->addModule(new MigrationModule());

$kernel->boot();
```

Each module class may only be registered once. Registering the same class twice throws a `ModuleException`. `addModule()` also throws if called after boot has started.

`getModules(): list<class-string<ModuleInterface>>` returns the class names of every module added so far — available immediately, before `boot()` is even called:

```php
$kernel->addModule(new DatabaseModule());

$kernel->getModules(); // [DatabaseModule::class]
```

#### Aggregate Modules

A module can compose and cascade-load other modules by implementing `AggregateModuleInterface` — useful for a package that wants a single `addModule()` call to pull in everything it needs, including conditionally based on the environment:

```php
use Georgeff\Kernel\Contract\AggregateModuleInterface;
use Georgeff\Kernel\Contract\EnvironmentInterface;
use Georgeff\Kernel\Contract\ModuleInterface;
use Georgeff\Kernel\KernelInterface;

final class DatabaseModule implements AggregateModuleInterface
{
    public function register(KernelInterface $kernel): void
    {
        // register this module's own services, if any
    }

    public function modules(EnvironmentInterface $env): array
    {
        $modules = [new MigrationModule()];

        if (!$env->is('production')) {
            $modules[] = new DatabaseDebugModule();
        }

        return $modules;
    }
}
```

```php
$kernel->addModule(new DatabaseModule());
// MigrationModule (and DatabaseDebugModule outside production) are loaded automatically.
```

Aggregate expansion is recursive — a module returned by `modules()` can itself be an aggregate. The same module-dedup guard used for `addModule()` protects against accidental cycles.

#### Boot Phase Order

When `boot()` is called, the kernel proceeds through these phases in order:

1. `onBooting` callbacks
2. **Module load** — aggregate modules are expanded; `config()` is called on all `ConfigurableModuleInterface` modules and the merged result becomes `Config\ConfigInterface`
3. **Module registration** — `register()` is called on all modules
4. **Service fallbacks** — pending `defineFallback()` definitions are added for any id that's still undefined
5. **Service overrides** — pending overrides are applied
6. **Service decoration** — pending decorators are applied
7. Container initialization
8. **Module boot** — `boot()` is called on all `BootableModuleInterface` modules
9. `onBooted` callbacks
10. **Garbage collection** — boot-only working state is released

### Lifecycle Callbacks

The kernel provides four hooks for tapping into the boot and shutdown lifecycle. All callbacks receive the full `KernelInterface` instance and all hook methods return the kernel for fluent chaining.

#### Boot callbacks

`onBooting` runs before service definitions are registered with the container. Use it to add definitions dynamically or configure the kernel before boot:

```php
$kernel->onBooting(function (KernelInterface $kernel) {
    $kernel->define('dynamic', fn() => new SomeService())->share();
});
```

`onBooted` runs after boot completes. The container is available at this point:

```php
$kernel->onBooted(function (KernelInterface $kernel) {
    $kernel->getContainer()->get('logger')->info('Kernel booted');
});
```

Both must be registered before `boot()` is called.

Boot callbacks fail fast: if a callback throws, the remaining callbacks in that hook are never called and the exception propagates immediately, aborting `boot()`. A thrown `KernelExceptionInterface` or PSR-11 `ContainerExceptionInterface` propagates as-is; anything else is wrapped in a `HookException` with the original preserved as the previous exception. This matches the sequential, order-dependent nature of booting — there's no reason to keep registering services once an earlier step has already failed.

#### Shutdown callbacks

`onShutdown` runs before the kernel is marked as shut down. `afterShutdown` runs after. Both can be registered any time before `shutdown()` is called — including after boot:

```php
$kernel->onShutdown(function (KernelInterface $kernel) {
    // isShutdown() is still false here
});

$kernel->afterShutdown(function (KernelInterface $kernel) {
    // isShutdown() is true here
});
```

Shutdown callbacks behave differently from boot callbacks: every callback still runs even if an earlier one throws, since shutdown is cleanup — one broken hook (say, a failed cache flush) shouldn't prevent other unrelated cleanup (closing a DB connection, releasing a lock) from running. If any callbacks failed, a single `HookException` is thrown once every callback has had a chance to run, with a message aggregating every failure and the first failure preserved as the previous exception.

#### Resolution hooks

`onResolving` and `onResolved` tap into the container's own resolution lifecycle — a pre-resolution hook fired on every `get()` call (including cache hits) and a post-resolution hook fired only when a factory actually runs:

```php
$kernel->onResolving(function (string $id) {
    // about to resolve $id
});

$kernel->onResolved(function (string $id, mixed $resolved) {
    // $id was just resolved to $resolved via its factory
});
```

Both must be registered before `boot()` is called, and both throw `KernelException` if called after boot.

### Shutdown

Call `shutdown()` to run the shutdown lifecycle. It is idempotent and a no-op if the kernel has not been booted:

```php
$kernel->boot();

// handle a request, run a command, etc.

$kernel->shutdown();

$kernel->isShutdown(); // true
```

Shutdown runs in this order:

1. `onShutdown` callbacks
2. The container is released and resolved-service-reset tracking is cleared
3. Kernel marked as shut down (`isShutdown()` becomes `true`)
4. `afterShutdown` callbacks

If an `onShutdown` callback throws, none of the following steps run — the container isn't released, `isShutdown()` stays `false`, and `afterShutdown` callbacks don't run. Shutdown either completes in full or is treated as not having happened at all, so a caller that catches the failure still has a working kernel rather than a half-torn-down one.

After shutdown, `getContainer()` and `resetServices()` both throw `KernelException` — there's nothing left to resolve or reset.

### Resetting Services

For long-running processes (workers, daemons) that want a clean slate between units of work, `resetServices()` resets every resolved shared service that implements `Contract\ResettableInterface` back to its original state:

```php
use Georgeff\Kernel\Contract\ResettableInterface;

final class ConnectionPool implements ResettableInterface
{
    public function reset(): void
    {
        // clear internal state
    }
}

$kernel->define(ConnectionPool::class, fn() => new ConnectionPool())->share();
$kernel->boot();

$kernel->getContainer()->get(ConnectionPool::class);

$kernel->resetServices();
// ConnectionPool::reset() was called automatically — no manual tagging required.
```

Detection is automatic: any resolved, shared service implementing `ResettableInterface` is tracked the moment it's resolved through the container. `resetServices()` throws `KernelException` if called before boot.

A service's `reset()` is allowed to fail up to a threshold before `resetServices()` gives up on it entirely and throws `ServiceResetException`. The default threshold is 3, passed as an argument:

```php
$kernel->resetServices(failureThreshold: 5);
```

An individual service can override the default by implementing `ThresholdAwareResettableInterface`:

```php
use Georgeff\Kernel\Contract\ThresholdAwareResettableInterface;

final class FlakyCache implements ThresholdAwareResettableInterface
{
    public function reset(): void { /* ... */ }

    public function getFailureThreshold(): int
    {
        return 1; // give up after the very first failure
    }
}
```

Failures are tracked per container id (not per class, so the same class backing two different ids is tracked independently) and accumulate across separate calls to `resetServices()` until a successful reset clears that service's failure history.

### Custom Container Builder

The kernel uses a `Contract\ContainerBuilderInterface` to register definitions with the underlying container. `DI\ContainerBuilder`, backed by `georgeff/container`, is used by default. Provide your own to use a different container implementation:

```php
$builder = new MyContainerBuilder();
$kernel = new Kernel(new Production(), $builder);
```

### Debug Mode

When debug mode is enabled, the kernel profiles the boot process and tracks service resolutions:

```php
$kernel = new Kernel(new Development(), debug: true);
$kernel->boot();

$kernel->getStartTime(); // float (microtime)
$kernel->getDebugInfo(); // boot profile + service resolution + resetter data
```

The `getDebugInfo()` array contains:

- `boot.profile` — timing for each boot phase
- `modules` — module loader state: which module classes were added and whether each phase has run
- `services` — which services have been resolved and which remain unresolved; each resolved entry includes a `resolutionCount` and, for services implementing `DebuggableInterface`, a `debugInfo` key
- `service.resetter` — failure counts and logged exception messages per service id, for services that have failed a `reset()` at least once
- `shutdown.profile` — timing for the `shutdown` and `afterShutdown` phases; only present once `shutdown()` has actually been called

When debug is disabled, `getStartTime()` returns `-INF` and `getDebugInfo()` returns `[]`.

#### DebuggableInterface

Services can implement `DebuggableInterface` to expose debug data. In debug mode, their `getDebugInfo()` output is collected automatically after each factory resolution and included in the kernel's debug info under `services.resolved`:

```php
use Georgeff\Kernel\Debug\DebuggableInterface;

final class ConnectionPool implements DebuggableInterface
{
    public function getDebugInfo(): array
    {
        return ['active' => $this->activeCount, 'idle' => $this->idleCount];
    }
}
```

### Exceptions

Every exception this package throws implements `Exception\KernelExceptionInterface`, so callers can catch one type regardless of which specific exception was thrown:

```php
use Georgeff\Kernel\Exception\KernelExceptionInterface;

try {
    $kernel->boot();
} catch (KernelExceptionInterface $e) {
    // KernelException, ModuleException, ConfigException, DefinitionException,
    // EnvironmentException, HookException, or ServiceResetException
}
```

`KernelException` (general kernel-state guards), `ModuleException` (module lifecycle), `ConfigException` (`Config::branch()`), `DefinitionException` (`DefinitionRepository` guards), `EnvironmentException` (`EnvironmentResolver`), `HookException` (a lifecycle callback failing — see [Lifecycle Callbacks](#lifecycle-callbacks)), and `ServiceResetException` (`resetServices()`) each provide static helpers via the shared `Exception\ThrowHelpers` trait:

```php
use Georgeff\Kernel\Exception\KernelException;

// Always throws
KernelException::throw('Something went wrong');

// Throws if $condition is true
KernelException::throwIf($this->isBooted(), 'Kernel is already booted');

// Throws if $condition is false
KernelException::throwIfNot($this->isBooted(), 'Kernel has not been booted');
```

Each accepts an optional second (or third, for `throwIf`/`throwIfNot`) `$previous` throwable. These are primarily useful when authoring custom kernel subclasses or modules that need guard conditions consistent with the kernel's own error types.

### Extending the Kernel

The `Kernel` class can be extended for specialized use cases such as HTTP or console kernels. `Contract\RunnableKernelInterface extends KernelInterface` is provided for kernels that serve as an application entry point:

```php
use Georgeff\Kernel\Contract\RunnableKernelInterface;

class ConsoleKernel extends Kernel implements RunnableKernelInterface
{
    public function run(): int
    {
        $this->boot();

        // dispatch console command...

        return 0;
    }
}
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

MIT
