<?php

namespace Georgeff\Kernel;

use Psr\Container\ContainerInterface;
use Georgeff\Kernel\DI\DefinitionInterface;
use Georgeff\Kernel\Module\ModuleInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Georgeff\Kernel\Module\ModuleRepositoryInterface;

class Kernel implements KernelInterface, Debug\DebuggableInterface
{
    protected ?float $startTime = null;

    protected ?Debug\Profiler $bootProfile = null;

    private DI\DefinitionRepository $definitions;

    /**
     * @var array<string, string[]>
     */
    private array $tags = [];

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

    private bool $booting = false;

    private bool $shutdown = false;

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
        $this->modules     = new Module\ModuleLoader();
        $this->definitions = new DI\DefinitionRepository();

        $this->registerDefaultDefinitions();
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
        $this->definitions->add(KernelInterface::class, fn() => $this)->share()->alias('kernel');
        $this->definitions->add('kernel.debug', fn() => $this->isDebug())->share();
        $this->definitions->add('kernel.environment', fn() => $this->getEnvironment())->share();
        $this->definitions
             ->add(DI\TagRegistryInterface::class, fn(ContainerInterface $c) => new DI\TagRegistry($c, $this->tags))
             ->share()
             ->alias('kernel.tag.registry');

        $this->reservedServices = [
            KernelInterface::class,
            DI\TagRegistryInterface::class,
            'kernel',
            'kernel.config',
            'kernel.debug',
            'kernel.environment',
            'kernel.tag.registry',
        ];
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

        $this->booting = true;

        $this->profile('moduleLoad', function () {
            $config = $this->modules->load($this->environment);

            $this->definitions->add('kernel.config', fn() => $config)->share();
        });

        $this->profile('moduleRegistration', function () {
            $this->modules->register($this);
        });

        $this->profile('serviceDecoration', function () {
            $this->definitions->applyDecorators();
        });

        $this->profile('serviceRegistration', function () {
            foreach ($this->definitions->all() as $definition) {
                $id      = $definition->getId();
                $aliases = $definition->getAliases();

                if (!in_array($id, $this->reservedServices, true) && array_intersect($this->reservedServices, $aliases)) {
                    throw new KernelException('Cannot overwrite a reserved service definition');
                }

                $this->registrar->register($id, $definition->getFactory(), $definition->isShared(), $aliases);

                foreach ($definition->getTags() as $tag) {
                    if (!in_array($id, $this->tags[$tag] ?? [], true)) {
                        $this->tags[$tag][] = $id;
                    }
                }
            }
        });

        $this->profile('containerInit', function () {
            $this->container = $this->registrar->getContainer();

            if (null !== $this->bootProfile) {
                $this->container = new Debug\DebugContainer($this->container, $this->definitions->getRaw());
            }
        });

        $this->profile('moduleBoot', function () {
            assert(null !== $this->container);

            $this->modules->boot($this->container);
        });

        $this->booted = true;

        $this->booting = false;

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
     * @inheritdoc
     */
    public function addDefinition(string $id, callable $factory, bool $shared = false, array $aliases = [], array $tags = []): static
    {
        $definition = $this->define($id, $factory);

        if ($shared) {
            $definition->share();
        }

        foreach ($aliases as $alias) {
            $definition->alias($alias);
        }

        foreach ($tags as $tag) {
            $definition->tag($tag);
        }

        return $this;
    }

    public function define(string $id, callable $factory): DefinitionInterface
    {
        if ($this->isBooted()) {
            throw new KernelException('Kernel has already been booted, cannot add new container definitions');
        }

        if (in_array($id, $this->reservedServices, true)) {
            throw new KernelException('Cannot overwrite a reserved service definition');
        }

        return $this->definitions->add($id, $factory);
    }

    /**
     * @inheritdoc
     */
    public function tag(string $id, array $tags): static
    {
        if ($this->isBooted()) {
            throw new KernelException('Kernel has already been booted, cannot add new container definition tags');
        }

        $definition = $this->definitions->get($id);

        if (null !== $definition) {
            foreach ($tags as $tag) {
                $definition->tag($tag);
            }
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function decorate(string $id, callable $decorator): static
    {
        if ($this->isBooted()) {
            throw new KernelException('Kernel has already been booted, cannot add new definition decorators');
        }

        if (in_array($id, $this->reservedServices, true)) {
            throw new KernelException('Cannot decorate a reserved service definition');
        }

        $this->definitions->decorate($id, $decorator);

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

        if ($this->isBooting()) {
            throw new KernelException('Cannot add modules after the kernel has started booting');
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

        if ($this->isBooting()) {
            throw new KernelException('Cannot add module repository after the kernel has started booting');
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
