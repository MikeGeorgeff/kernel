# Kernel

A lightweight application kernel with service container bootstrapping, module system, lifecycle callbacks, and PSR-14 event dispatching.

## Installation

```bash
composer require georgeff/kernel
```

## Usage

### Basic Bootstrapping

```php
use Georgeff\Kernel\Environment;
use Georgeff\Kernel\Kernel;

$kernel = new Kernel(Environment::Production);

$kernel
    ->addDefinition('logger', fn() => new FileLogger('/var/log/app.log'), shared: true)
    ->addDefinition('mailer', fn() => new SmtpMailer('localhost'), shared: true);

$kernel->boot();

$container = $kernel->getContainer();
$logger = $container->get('logger');
```

### Environments

The `Environment` enum provides five application environments:

- `Environment::Production`
- `Environment::Staging`
- `Environment::Development`
- `Environment::Testing`
- `Environment::Local`

`Local` is for local development machines. `Development` is the remote dev/integration tier.

```php
$kernel = new Kernel(Environment::Local, debug: true);

$kernel->getEnvironment(); // 'local'
$kernel->isDebug();        // true
```

### Service Definitions

Register service definitions before booting. Each definition takes a factory callable, an optional shared flag, and optional aliases:

```php
$kernel->addDefinition(
    'db.connection',
    fn() => new PdoConnection($dsn, $user, $pass),
    shared: true,
    aliases: [ConnectionInterface::class],
);
```

Definitions registered later with the same ID will overwrite earlier ones, allowing base definitions to be overridden.

### Fluent Definition Builder

`define()` is an alternative to `addDefinition()` that returns the definition for fluent configuration:

```php
$kernel->define('db.connection', fn() => new PdoConnection($dsn, $user, $pass))
    ->share()
    ->alias(ConnectionInterface::class)
    ->tag('db.connections');
```

The builder methods are:

| Method | Description |
|---|---|
| `share()` | Register the service as a singleton |
| `alias(string $alias)` | Add a container alias |
| `tag(string $tag)` | Add a tag |

All three return the same definition instance, so they can be chained in any order. `addDefinition()` remains available for cases where all options are known upfront.

### Definition Tags

Tags group service definitions under a shared label so they can be collected and resolved together. Pass a `tags` array to `addDefinition()`:

```php
$kernel->addDefinition(
    FirstMiddleware::class,
    fn() => new FirstMiddleware(),
    shared: true,
    tags: ['http.middleware'],
);

$kernel->addDefinition(
    SecondMiddleware::class,
    fn() => new SecondMiddleware(),
    shared: true,
    tags: ['http.middleware'],
);
```

Retrieve all services for a tag via `TagRegistryInterface` after boot:

```php
use Georgeff\Kernel\DI\TagRegistryInterface;

$kernel->boot();

$registry   = $kernel->getContainer()->get(TagRegistryInterface::class);
$middleware = $registry->getTagged('http.middleware');
// [FirstMiddleware, SecondMiddleware] — resolved in registration order
```

`tag()` is available as a standalone method for cases where the definition comes from another module or package:

```php
final class MiddlewareModule implements ModuleInterface
{
    public function register(KernelInterface $kernel): void
    {
        // Tag a service defined by a different module
        $kernel->tag(RouterMiddleware::class, ['http.middleware']);
    }
}
```

Both `addDefinition()` and `tag()` throw `KernelException` if called after boot. Registering the same ID/tag pair more than once is idempotent.

### Service Decoration

`decorate()` wraps an existing service definition with a decorator. The decorator callable receives the resolved inner service and the container:

```php
$kernel->addDefinition(
    LoggerInterface::class,
    fn() => new FileLogger('/var/log/app.log'),
    shared: true,
);

$kernel->decorate(
    LoggerInterface::class,
    fn(LoggerInterface $inner, ContainerInterface $c) => new TimestampLogger($inner),
);

$kernel->boot();

$logger = $kernel->getContainer()->get(LoggerInterface::class);
// TimestampLogger wrapping FileLogger
```

The decorated service automatically inherits the original's shared flag, aliases, and tags — existing consumers resolve the decorated version transparently.

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

`decorate()` throws `KernelException` if called after boot, for a reserved service ID, or if the same ID is decorated more than once. A `KernelException` is also thrown at boot time if the target definition does not exist.

### Modules

Modules are self-contained units that contribute service definitions, configuration, and boot logic to the kernel. They replace ad-hoc `addDefinition()` calls with composable, reusable pieces.

#### Defining a Module

Every module implements `ModuleInterface` with a single `register()` method:

```php
use Georgeff\Kernel\KernelInterface;
use Georgeff\Kernel\Module\ModuleInterface;

final class DatabaseModule implements ModuleInterface
{
    public function register(KernelInterface $kernel): void
    {
        $kernel->addDefinition(
            'db.connection',
            fn() => new PdoConnection(getenv('DB_DSN')),
            shared: true,
            aliases: [ConnectionInterface::class],
        );
    }
}
```

#### Configuration

Modules that need to declare configuration implement `ConfigurableModuleInterface`. The returned array is merged into the `kernel.config` container service during boot:

```php
use Georgeff\Kernel\Environment;
use Georgeff\Kernel\KernelInterface;
use Georgeff\Kernel\Module\ConfigurableModuleInterface;

final class DatabaseModule implements ConfigurableModuleInterface
{
    public function register(KernelInterface $kernel): void
    {
        $kernel->addDefinition(
            'db.connection',
            fn(ContainerInterface $c) => new PdoConnection($c->get('db.dsn')),
            shared: true,
        );
    }

    public function config(Environment $env): array
    {
        return [
            'db.dsn'  => getenv('DB_DSN') ?: 'sqlite::memory:',
            'db.host' => getenv('DB_HOST') ?: 'localhost',
        ];
    }
}
```

The `$env` parameter is available for structural differences — for example, swapping a real driver for an in-memory one in testing:

```php
public function config(Environment $env): array
{
    return [
        'db.dsn' => $env === Environment::Testing
            ? 'sqlite::memory:'
            : getenv('DB_DSN'),
    ];
}
```

Config from multiple modules is merged in registration order. Later definitions overwrite earlier ones for the same key.

`Env::get()` is available for reading environment variables with automatic type coercion — useful in `config()` implementations:

```php
use Georgeff\Kernel\Support\Env;

public function config(Environment $env): array
{
    return [
        'db.dsn'  => Env::get('DB_DSN', 'sqlite::memory:'),
        'db.port' => Env::get('DB_PORT', '3306'),
        'db.log'  => Env::get('DB_LOG', false),
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

#### Module Boot

Modules that need access to the built container implement `BootableModuleInterface`. `boot()` is called after the container is fully initialized:

```php
use Georgeff\Kernel\KernelInterface;
use Georgeff\Kernel\Module\BootableModuleInterface;
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

#### Registering Modules

Register modules on the kernel before booting:

```php
$kernel = new Kernel(Environment::Production);

$kernel
    ->addModule(new DatabaseModule())
    ->addModule(new CacheModule())
    ->addModule(new MigrationModule());

$kernel->boot();
```

Each module class may only be registered once. Registering the same class twice throws a `KernelException`.

#### Module Repositories

Repositories group related modules and can conditionally include them based on the environment. Packages ship a repository rather than exposing individual modules:

```php
use Georgeff\Kernel\Environment;
use Georgeff\Kernel\Module\ModuleInterface;
use Georgeff\Kernel\Module\ModuleRepositoryInterface;

final class DatabaseRepository implements ModuleRepositoryInterface
{
    public function modules(Environment $env): array
    {
        $modules = [
            new DatabaseModule(),
            new MigrationModule(),
        ];

        if ($env !== Environment::Production) {
            $modules[] = new DatabaseDebugModule();
        }

        return $modules;
    }
}
```

```php
$kernel->addRepository(new DatabaseRepository());
```

#### Boot Phase Order

When `boot()` is called, modules are processed in this order:

1. `onBooting` callbacks
2. **Module load** — repositories are flattened into the module list; `config()` is called on all `ConfigurableModuleInterface` modules and the result is bound as `kernel.config`
3. **Module registration** — `register()` is called on all modules
4. **Service decoration** — pending decorators are applied after all modules have registered
5. Container initialization
6. **Module boot** — `boot()` is called on all `BootableModuleInterface` modules
7. `KernelBooted` event dispatched + `onBooted` callbacks

### Lifecycle Callbacks

The kernel provides four hooks for tapping into the boot and shutdown lifecycle. All callbacks receive the full `KernelInterface` instance and all hook methods return the kernel for fluent chaining.

#### Boot callbacks

`onBooting` runs before service definitions are registered with the container. Use it to add definitions dynamically or configure the kernel before boot:

```php
$kernel->onBooting(function (KernelInterface $kernel) {
    $kernel->addDefinition('dynamic', fn() => new SomeService(), shared: true);
});
```

`onBooted` runs after boot completes and the `KernelBooted` event has been dispatched. The container is available at this point:

```php
$kernel->onBooted(function (KernelInterface $kernel) {
    $kernel->getContainer()->get('logger')->info('Kernel booted');
});
```

Both must be registered before `boot()` is called.

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
2. Kernel marked as shut down (`isShutdown()` becomes `true`)
3. `afterShutdown` callbacks

### Events

After boot completes, the kernel dispatches a `KernelBooted` event via PSR-14 if an `EventDispatcherInterface` is registered in the container:

```php
use Georgeff\Kernel\Event\KernelBooted;
use Psr\EventDispatcher\EventDispatcherInterface;

$kernel->addDefinition(
    EventDispatcherInterface::class,
    fn() => new MyEventDispatcher(),
    shared: true,
);

$kernel->boot(); // dispatches KernelBooted

// In your listener:
function handleBooted(KernelBooted $event): void {
    $kernel = $event->kernel; // readonly public property
}
```

If no `EventDispatcherInterface` is registered, boot completes without dispatching.

### Custom Service Registrar

The kernel uses a `ServiceRegistrar` interface to register definitions with the container. A `DefaultServiceRegistrar` backed by `georgeff/container` is used by default. Provide your own to use a different container implementation:

```php
$registrar = new MyServiceRegistrar();
$kernel = new Kernel(Environment::Production, $registrar);
```

### Debug Mode

When debug mode is enabled, the kernel profiles the boot process, wraps the container in a `DebugContainer` that tracks service resolutions, and collects debug info from any resolved service implementing `DebuggableInterface`:

```php
$kernel = new Kernel(Environment::Development, debug: true);
$kernel->boot();

$kernel->getStartTime(); // float (microtime)
$kernel->getDebugInfo(); // boot profile + service resolution data
```

The `getDebugInfo()` array contains:

- `bootProfile` — timing for each boot phase (`preBoot`, `moduleLoad`, `moduleRegistration`, `serviceDecoration`, `serviceRegistration`, `containerInit`, `moduleBoot`)
- `modules` — module loader state: which module classes were loaded and whether each phase has run
- `serviceResolutionProfile` — which services were resolved and their resolution times
- `servicesDebugInfo` — debug info collected from resolved services that implement `DebuggableInterface`

When debug is disabled, `getStartTime()` returns `-INF` and `getDebugInfo()` returns `[]`.

#### DebuggableInterface

Services can implement `DebuggableInterface` to expose debug data. When resolved through the debug container, their `getDebugInfo()` output is collected automatically:

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

### KernelException Helpers

`KernelException` provides three static helpers for throwing from guard conditions without an inline `if`:

```php
use Georgeff\Kernel\KernelException;

// Always throws
KernelException::throw('Something went wrong');

// Throws if $condition is true
KernelException::throwIf($this->isBooted(), 'Kernel is already booted');

// Throws if $condition is false
KernelException::throwIfNot($this->isBooted(), 'Kernel has not been booted');
```

These are primarily useful when authoring custom kernel subclasses or modules that need guard conditions consistent with the kernel's own error type.

### Reserved Services

The kernel registers the following services in the container during boot:

- `kernel` (aliased to `KernelInterface`)
- `kernel.environment` — the environment string value (e.g. `'production'`)
- `kernel.debug` — the debug flag (`bool`)
- `kernel.config` — the merged config array from all `ConfigurableModuleInterface` modules (`[]` if none)
- `kernel.tag.registry` (aliased to `TagRegistryInterface`) — the tag registry

These IDs cannot be overwritten via `addDefinition`. The `kernel.*` namespace is reserved for the kernel — any service ID with that prefix should be considered owned by the package and subject to change between minor versions.

### Extending the Kernel

The `Kernel` class can be extended for specialized use cases such as HTTP or console kernels. A `RunnableKernelInterface` is provided for kernels that serve as an application entry point:

```php
use Georgeff\Kernel\RunnableKernelInterface;

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
