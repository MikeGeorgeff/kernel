<?php

namespace Georgeff\Kernel;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

class Kernel implements KernelInterface, Debug\DebuggableInterface
{
    protected ?float $startTime = null;

    protected ?Debug\Profiler $bootProfile = null;

    /**
     * @var array<string, array{factory: callable, shared: bool, aliases: string[]}>
     */
    private array $definitions = [];

    private ServiceRegistrar $registrar;

    protected ?ContainerInterface $container = null;

    protected Environment $environment;

    protected bool $debug;

    protected bool $booted = false;

    /**
     * @var array<callable(KernelInterface): void>
     */
    private array $preBootCallbacks = [];

    public function __construct(
        Environment $environment,
        ?ServiceRegistrar $registrar = null,
        bool $debug = false,
    ) {
        $this->environment = $environment;
        $this->registrar   = $registrar ?: new DefaultServiceRegistrar();
        $this->debug       = $debug;
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
        $this->definitions['kernel']             = ['factory' => fn() => $this, 'shared' => true, 'aliases' => [KernelInterface::class]];
        $this->definitions['kernel.debug']       = ['factory' => fn() => $this->isDebug(), 'shared' => true, 'aliases' => []];
        $this->definitions['kernel.environment'] = ['factory' => fn() => $this->getEnvironment(), 'shared' => true, 'aliases' => []];
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

        $this->profile('serviceRegistration', function () {
            $this->registerDefaultDefinitions();

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

        $this->booted = true;

        $this->dispatchKernelEvent(new Event\KernelBooted($this));

        $this->bootProfile?->stop();
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
    public function addDefinition(string $id, callable $factory, bool $shared = false, array $aliases = []): static
    {
        if ($this->isBooted()) {
            throw new KernelException('Kernel has already been booted, cannot add new container definitions');
        }

        $reserved = ['kernel', KernelInterface::class, 'kernel.environment', 'kernel.debug'];

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
    public function getContainer(): ContainerInterface
    {
        if (!$this->booted || !$this->container) {
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
        }

        if ($this->container instanceof Debug\DebuggableInterface) {
            $info += $this->container->getDebugInfo();
        }

        return $info;
    }
}
