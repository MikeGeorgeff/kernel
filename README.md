# Kernel

A lightweight application kernel with service container bootstrapping, lifecycle callbacks, and PSR-14 event dispatching.

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

The `Environment` enum provides four application environments:

- `Environment::Production`
- `Environment::Staging`
- `Environment::Development`
- `Environment::Testing`

```php
$kernel = new Kernel(Environment::Development, debug: true);

$kernel->getEnvironment(); // 'development'
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

### Lifecycle Callbacks

Register `onBooting` callbacks to hook into the kernel boot lifecycle. Callbacks run before service definitions are registered with the container:

```php
$kernel = new Kernel(Environment::Production);

$kernel->onBooting(function (KernelInterface $kernel) {
    // Called during boot, before services are registered
});

$kernel->boot();
```

`onBooting` callbacks must be registered before boot. They can also add definitions dynamically:

```php
$kernel->onBooting(function (KernelInterface $kernel) {
    $kernel->addDefinition('dynamic', fn() => new SomeService(), shared: true);
});
```

`onBooting` returns the kernel for fluent chaining with `addDefinition`:

```php
$kernel
    ->onBooting(function (KernelInterface $kernel) { /* ... */ })
    ->addDefinition('logger', fn() => new FileLogger(), shared: true);
```

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

- `bootProfile` — timing for each boot phase (`preBoot`, `serviceRegistration`, `containerInit`)
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

### Reserved Services

The kernel registers the following services in the container during boot:

- `kernel` (aliased to `KernelInterface`)
- `kernel.environment` — the environment string value (e.g. `'production'`)
- `kernel.debug` — the debug flag (`bool`)

These IDs cannot be overwritten via `addDefinition`.

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

## License

MIT
