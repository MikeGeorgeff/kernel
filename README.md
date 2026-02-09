# Kernel

A lightweight application kernel with service container bootstrapping and lifecycle events.

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

Register callbacks to hook into the kernel boot lifecycle:

```php
$kernel = new Kernel(Environment::Production);

$kernel
    ->onBooting(function (KernelInterface $kernel) {
        // Called during boot, before services are registered
        // $kernel->isBooting() === true
    })
    ->onBooted(function (KernelInterface $kernel) {
        // Called after boot completes
        // $kernel->isBooted() === true
        // $kernel->getContainer() is accessible
    });

$kernel->boot();
```

`onBooting` callbacks must be registered before boot. `onBooted` callbacks can be registered before or during boot, which allows an `onBooting` callback to register additional `onBooted` callbacks:

```php
$kernel->onBooting(function (KernelInterface $kernel) {
    $kernel->onBooted(function (KernelInterface $kernel) {
        // runs after boot completes
    });
});
```

Both methods return the kernel for fluent chaining with `addDefinition`:

```php
$kernel
    ->onBooting(function (KernelInterface $kernel) { /* ... */ })
    ->addDefinition('logger', fn() => new FileLogger(), shared: true)
    ->onBooted(function (KernelInterface $kernel) { /* ... */ });
```

### Custom Service Registrar

The kernel uses a `ServiceRegistrar` interface to register definitions with the container. A `DefaultServiceRegistrar` backed by `georgeff/container` is used by default. Provide your own to use a different container implementation:

```php
$registrar = new MyServiceRegistrar();
$kernel = new Kernel(Environment::Production, $registrar);
```

### Reserved Services

The kernel registers itself in the container during boot:

- `kernel` (aliased to `KernelInterface`)

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
