<?php

namespace Georgeff\Kernel;

use Psr\Container\ContainerInterface;
use Georgeff\Kernel\Module\ModuleInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Georgeff\Kernel\Module\ModuleRepositoryInterface;

class Kernel implements KernelInterface, Debug\DebuggableInterface
{
    protected ?float $startTime = null;

    protected ?Debug\Profiler $bootProfile = null;

    /**
     * @var array<string, array{factory: callable, shared: bool, aliases: string[]}>
     */
    private array $definitions = [];

    /**
     * @var string[]
     */
    private array $reservedServices = [];

    private Module\ModuleLoader $modules;

    private ServiceRegistrar $registrar;

    protected ?ContainerInterface $container = null;

    protected Environment $environment;

    protected bool $debug;

    /**
     * @internal
     */
    protected bool $booted = false;

    private bool $shutdown = false;

    private bool $lockModules = false;

    /**
     * @var array<callable(KernelInterface): void>
     */
    private array $preBootCallbacks = [];

    /**
     * @var array<callable(KernelInterface): void>
     */
    private array $postBootCallbacks = [];

    /**
     * @var array<callable(KernelInterface): void>
     */
    private array $preShutdownCallbacks = [];

    /**
     * @var array<callable(KernelInterface): void>
     */
    private array $postShutdownCallbacks = [];

    public function __construct(
        Environment $environment,
        ?ServiceRegistrar $registrar = null,
        bool $debug = false,
    ) {
        $this->environment = $environment;
        $this->registrar   = $registrar ?: new DefaultServiceRegistrar();
        $this->debug       = $debug;

        $this->registerDefaultDefinitions();

        $this->modules = new Module\ModuleLoader();
    }

    protected function dispatchKernelEvent(Event\KernelEvent $event): void
    {
        if ($this->container && $this->container->has(EventDispatcherInterface::class)) {
            /** @var \Psr\EventDispatcher\EventDispatcherInterface $dispatcher */
            $dispatcher = $this->container->get(EventDispatcherInterface::class);

            $dispatcher->dispatch($event);
        }
    }

    private function initProfiler(): void
    {
        if (!$this->isDebug()) {
            return;
        }

        $this->bootProfile = new Debug\Profiler();

        $this->startTime   = $this->bootProfile->start();
    }

    private function profile(string $phase, callable $fn): void
    {
        $this->bootProfile?->startPhase($phase);

        $fn();

        $this->bootProfile?->stopPhase($phase);
    }

    private function registerDefaultDefinitions(): void
    {
        $this->addReserved(KernelInterface::class, $this, true, ['kernel'])
             ->addReserved('kernel.debug', $this->isDebug(), true)
             ->addReserved('kernel.environment', $this->getEnvironment(), true);

        $this->reservedServices[] = 'kernel.config';
    }

    /**
     * @inheritdoc
     */
    public function boot(): void
    {
        if ($this->isBooted()) {
            return;
        }

        $this->initProfiler();

        $this->profile('preBoot', function () {
            foreach ($this->preBootCallbacks as $callback) {
                $callback($this);
            }
        });

        $this->lockModules = true;

        $this->profile('moduleLoad', function () {
            $this->addReserved('kernel.config', $this->modules->load($this->environment), true);
        });

        $this->profile('moduleRegistration', function () {
            $this->modules->register($this);
        });

        $this->profile('serviceRegistration', function () {
            foreach ($this->definitions as $id => $definition) {
                $this->registrar->register(
                    $id,
                    $definition['factory'],
                    $definition['shared'],
                    $definition['aliases']
                );
            }
        });

        $this->profile('containerInit', function () {
            $this->container = $this->registrar->getContainer();

            if ($this->bootProfile !== null) {
                $this->container = new Debug\DebugContainer($this->container, $this->definitions);
            }
        });

        $this->profile('moduleBoot', function () {
            assert(null !== $this->container);

            $this->modules->boot($this->container);
        });

        $this->booted = true;

        $this->profile('postBoot', function () {
            $this->dispatchKernelEvent(new Event\KernelBooted($this));

            foreach ($this->postBootCallbacks as $callback) {
                $callback($this);
            }
        });

        $this->bootProfile?->stop();
    }

    /**
     * @inheritdoc
     */
    public function shutdown(): void
    {
        if ($this->isShutdown()) {
            return;
        }

        if (!$this->isBooted()) {
            return;
        }

        foreach ($this->preShutdownCallbacks as $callback) {
            $callback($this);
        }

        $this->shutdown = true;

        foreach ($this->postShutdownCallbacks as $callback) {
            $callback($this);
        }
    }

    /**
     * @inheritdoc
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * @inheritdoc
     */
    public function isShutdown(): bool
    {
        return $this->shutdown;
    }

    /**
     * @inheritdoc
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * @inheritdoc
     */
    public function getEnvironment(): string
    {
        return $this->environment->value;
    }

    /**
     * @inheritdoc
     */
    public function onBooting(callable $callback): static
    {
        if ($this->isBooted()) {
            throw new KernelException('Kernel has already been booted, cannot add new pre-boot callbacks');
        }

        $this->preBootCallbacks[] = $callback;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function onBooted(callable $callback): static
    {
        if ($this->isBooted()) {
            throw new KernelException('Kernel has already been booted, cannot add new post-boot callbacks');
        }

        $this->postBootCallbacks[] = $callback;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function onShutdown(callable $callback): static
    {
        if ($this->isShutdown()) {
            throw new KernelException('Kernel has already been shutdown, cannot add new pre-shutdown callbacks');
        }

        $this->preShutdownCallbacks[] = $callback;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function afterShutdown(callable $callback): static
    {
        if ($this->isShutdown()) {
            throw new KernelException('Kernel has already been shutdown, cannot add new post-shutdown callbacks');
        }

        $this->postShutdownCallbacks[] = $callback;

        return $this;
    }

    /**
     * Add a reserved definition
     *
     * @param string[] $aliases
     */
    private function addReserved(string $id, mixed $instance, bool $shared = false, array $aliases = []): static
    {
        $this->definitions[$id] = [
            'factory' => fn() => $instance,
            'shared'  => $shared,
            'aliases' => $aliases,
        ];

        foreach ([$id, ...$aliases] as $serviceId) {
            if (!in_array($serviceId, $this->reservedServices, true)) {
                $this->reservedServices[] = $serviceId;
            }
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function addDefinition(string $id, callable $factory, bool $shared = false, array $aliases = []): static
    {
        if ($this->isBooted()) {
            throw new KernelException('Kernel has already been booted, cannot add new container definitions');
        }

        $reserved = $this->reservedServices;

        if (in_array($id, $reserved, true) || array_intersect($reserved, $aliases)) {
            throw new KernelException('Cannot overwrite a reserved service definition');
        }

        $this->definitions[$id] = [
            'factory' => $factory,
            'shared'  => $shared,
            'aliases' => $aliases,
        ];

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function addModule(ModuleInterface $module): static
    {
        if ($this->isBooted()) {
            throw new KernelException('Kernel has already been booted, cannot add new modules');
        }

        if ($this->lockModules) {
            throw new KernelException('Cannot add modules, modules are locked');
        }

        $this->modules->add($module);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function addRepository(ModuleRepositoryInterface $repository): static
    {
        if ($this->isBooted()) {
            throw new KernelException('Kernel has already been booted, cannot add new module repositories');
        }

        if ($this->lockModules) {
            throw new KernelException('Cannot add module repository, modules are locked');
        }

        $this->modules->addRepository($repository);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getContainer(): ContainerInterface
    {
        if (!$this->isBooted() || !$this->container) {
            throw new KernelException('Container is inaccessible, kernel has not been booted');
        }

        return $this->container;
    }

    /**
     * @inheritdoc
     */
    public function getStartTime(): float
    {
        return $this->isDebug() && null !== $this->startTime ? $this->startTime : -INF;
    }

    /**
     * @inheritdoc
     */
    public function getDebugInfo(): array
    {
        if (!$this->isDebug()) {
            return [];
        }

        $info = [];

        if ($this->bootProfile !== null) {
            $info['bootProfile'] = $this->bootProfile->getDebugInfo();
            $info['modules']     = $this->modules->getDebugInfo();
        }

        if ($this->container instanceof Debug\DebuggableInterface) {
            $info += $this->container->getDebugInfo();
        }

        return $info;
    }
}
