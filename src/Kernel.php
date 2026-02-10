<?php

namespace Georgeff\Kernel;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

class Kernel implements KernelInterface
{
    protected ?float $startTime = null;

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

    private function preBoot(): void
    {
        if ($this->isDebug()) {
            $this->startTime = microtime(true);
        }

        foreach ($this->preBootCallbacks as $callback) {
            $callback($this);
        }
    }

    /**
     * @inheritdoc
     */
    public function boot(): void
    {
        if ($this->isBooted()) {
            return;
        }

        $this->preBoot();

        $this->registrar->register('kernel', fn() => $this, true, [KernelInterface::class]);
        $this->registrar->register('kernel.environment', fn() => $this->getEnvironment(), true);
        $this->registrar->register('kernel.debug', fn() => $this->isDebug(), true);

        foreach ($this->definitions as $id => $definition) {
            $this->registrar->register(
                $id,
                $definition['factory'],
                $definition['shared'],
                $definition['aliases']
            );
        }

        $this->container = $this->registrar->getContainer();

        $this->booted = true;

        $this->dispatchKernelEvent(new Event\KernelBooted($this));
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

        $reserved = ['kernel', KernelInterface::class];

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
}
