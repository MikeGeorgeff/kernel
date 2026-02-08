<?php

namespace Georgeff\Kernel;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

class Kernel implements KernelInterface
{
    /**
     * @var array<string, array{factory: callable, shared: bool, aliases: string[]}>
     */
    protected array $definitions = [];

    protected ServiceRegistrar $registrar;

    protected ?ContainerInterface $container = null;

    protected ?EventDispatcherInterface $event = null;

    protected Environment $environment;

    protected bool $debug;

    protected bool $booted = false;

    protected bool $booting = false;

    public function __construct(
        Environment $environment,
        ?ServiceRegistrar $registrar = null,
        ?EventDispatcherInterface $dispatcher = null,
        bool $debug = false,
    ) {
        $this->environment = $environment;
        $this->registrar   = $registrar ?: new DefaultServiceRegistrar();
        $this->event       = $dispatcher;
        $this->debug       = $debug;
    }

    /**
     * @inheritdoc
     */
    public function boot(): void
    {
        if ($this->isBooted()) {
            return;
        }

        $this->booting = true;

        $this->dispatchKernelEvent(new Event\KernelBooting($this));

        $this->registrar->register('kernel', fn() => $this, true, [KernelInterface::class]);

        if ($this->event) {
            $this->registrar->register('event.dispatcher', fn() => $this->event, true, [EventDispatcherInterface::class]);
        }

        foreach ($this->definitions as $id => $definition) {
            $this->registrar->register(
                $id,
                $definition['factory'],
                $definition['shared'],
                $definition['aliases']
            );
        }

        $this->container = $this->registrar->getContainer();

        $this->booting = false;

        $this->booted = true;

        $this->dispatchKernelEvent(new Event\KernelBooted($this));
    }

    /**
     * @inheritdoc
     */
    public function isBooting(): bool
    {
        return $this->booting;
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
    public function addDefinition(string $id, callable $factory, bool $shared = false, array $aliases = []): static
    {
        if ($this->isBooted()) {
            throw new KernelException('Kernel has already been booted, cannot add new container definitions');
        }

        $reserved = ['kernel', KernelInterface::class];

        if ($this->event) {
            array_push($reserved, 'event.dispatcher', EventDispatcherInterface::class);
        }

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

    protected function dispatchKernelEvent(Event\KernelEvent $event): void
    {
        if ($this->event) {
            $this->event->dispatch($event);
        }
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
}
