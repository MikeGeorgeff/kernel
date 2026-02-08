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

### Event Dispatching

Pass a PSR-14 event dispatcher to receive lifecycle events:

```php
$kernel = new Kernel(Environment::Production, dispatcher: $dispatcher);
```

Two events are dispatched during boot:

- `KernelBooting` - dispatched at the start of boot, before services are registered
- `KernelBooted` - dispatched after boot completes, container is accessible

When provided, the dispatcher is registered in the container as `event.dispatcher` with an `EventDispatcherInterface` alias.

### Custom Service Registrar

The kernel uses a `ServiceRegistrar` interface to register definitions with the container. A `DefaultServiceRegistrar` backed by `georgeff/container` is used by default. Provide your own to use a different container implementation:

```php
$registrar = new MyServiceRegistrar();
$kernel = new Kernel(Environment::Production, $registrar);
```

### Reserved Services

The kernel registers itself in the container during boot:

- `kernel` (aliased to `KernelInterface`)

When an event dispatcher is provided:

- `event.dispatcher` (aliased to `EventDispatcherInterface`)

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
