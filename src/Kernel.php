<?php

namespace Georgeff\Kernel;

use Psr\Container\ContainerInterface;
use Georgeff\Kernel\DI\DefinitionInterface;
use Georgeff\Kernel\Contract\ModuleInterface;
use Georgeff\Kernel\Exception\KernelException;
use Georgeff\Kernel\Contract\EnvironmentInterface;
use Georgeff\Kernel\Contract\ContainerBuilderInterface;

class Kernel implements KernelInterface
{
    private ?Debug\Profiler $bootProfile = null;

    private ?Debug\Profiler $shutdownProfile = null;

    private ?Debug\ServiceResolution $serviceResolution = null;

    private Contract\ContainerBuilderInterface $builder;

    private DI\DefinitionRepository $definitions;

    private Module\ModuleLoader $modules;

    private Hook\HookRepository $hooks;

    private DI\ServiceResetter $resetter;

    private ?ContainerInterface $container = null;

    private EnvironmentInterface $environment;

    private bool $debug;

    private bool $booted = false;

    private bool $booting = false;

    private bool $shutdown = false;

    /**
     * @var array<string, mixed>
     */
    private array $storage = [];

    public function __construct(
        EnvironmentInterface $environment,
        ?ContainerBuilderInterface $builder = null,
        bool $debug = false,
    ) {
        $this->environment = $environment;
        $this->builder     = $builder ?? new DI\ContainerBuilder();
        $this->debug       = $debug;
        $this->modules     = new Module\ModuleLoader();
        $this->definitions = new DI\DefinitionRepository();
        $this->hooks       = new Hook\HookRepository();
        $this->resetter    = new DI\ServiceResetter();
    }

    protected function initProfiler(): ?Debug\Profiler
    {
        if (!$this->isDebug()) {
            return null;
        }

        $profiler = new Debug\Profiler();

        $profiler->start();

        return $profiler;
    }

    private function profile(?Debug\Profiler $profile, string $phase, callable $fn): void
    {
        $profile?->startPhase($phase);

        try {
            $fn();
        } finally {
            $profile?->stopPhase($phase);
        }
    }

    public function boot(): void
    {
        if ($this->isBooted()) {
            return;
        }

        KernelException::throwIf($this->isBooting(), 'Kernel is booting, cannot call boot again');

        $this->bootProfile = $this->initProfiler();

        $this->profile($this->bootProfile, 'preBoot', function () {
            $this->hooks->invokeOnBootingCallbacks($this);
        });

        $this->booting = true;

        $this->profile($this->bootProfile, 'moduleLoad', function () {
            $this->storage['module.config'] = $this->modules->load($this->environment);
        });

        $this->profile($this->bootProfile, 'moduleRegistration', function () {
            $this->modules->register($this);
        });

        $this->profile($this->bootProfile, 'serviceFallbacks', function () {
            $this->definitions->addFallbacksToDefinitions();
        });

        $this->profile($this->bootProfile, 'serviceOverrides', function () {
            $this->definitions->applyOverrides();
        });

        $this->profile($this->bootProfile, 'serviceDecoration', function () {
            $this->definitions->applyDecorators();
        });

        $this->profile($this->bootProfile, 'serviceRegistration', function () {
            $tags = [];

            $this->storage['services.shared'] = [];

            foreach ($this->definitions->all() as $definition) {
                $id = $definition->getId();

                $this->builder->register($id, $definition->getFactory(), $definition->isShared(), $definition->getAliases());

                if ($definition->isShared()) {
                    $this->storage['services.shared'][$id] = true;
                }

                foreach ($definition->getTags() as $tag) {
                    $tags[$tag][] = $id;
                }
            }

            $this->builder->register(
                DI\TagRegistryInterface::class,
                fn(ContainerInterface $c) => new DI\TagRegistry($c, $tags),
                true
            );

            $this->builder->register(
                Config\ConfigInterface::class,
                function () {
                    /** @var array<string, mixed> */
                    $config = $this->storage['module.config'] ?? [];

                    unset($this->storage['module.config']);

                    return new Config\Config($config);
                },
                true
            );
        });

        $this->profile($this->bootProfile, 'containerInit', function () {
            if ($this->isDebug()) {
                $this->serviceResolution = new Debug\ServiceResolution($this->definitions->getRaw());

                $this->builder->onResolved(
                    function (string $id, mixed $resolved) {
                        assert(null !== $this->serviceResolution);

                        $this->serviceResolution->resolve($id, $resolved);
                    }
                );
            }

            /** @var array<string, bool> */
            $shared = $this->storage['services.shared'];

            $this->builder->onResolved(function (string $id, mixed $resolved) use ($shared) {
                if ($resolved instanceof Contract\ResettableInterface && isset($shared[$id])) {
                    $this->resetter->add($id, $resolved);
                }
            });

            $this->container = $this->builder->getContainer();
        });

        $this->profile($this->bootProfile, 'moduleBoot', function () {
            assert(null !== $this->container);

            $this->modules->boot($this->container);
        });

        $this->booted = true;

        $this->booting = false;

        $this->profile($this->bootProfile, 'postBoot', function () {
            $this->hooks->invokeOnBootedCallbacks($this);
        });

        $this->profile($this->bootProfile, 'garbageCollection', function () {
            $this->definitions->gc();
            $this->modules->gc();
            $this->hooks->gc();

            unset($this->storage['services.shared']);
        });

        $this->bootProfile?->stop();
    }

    /**
     * @inheritdoc
     */
    public function shutdown(): void
    {
        if ($this->isShutdown() || !$this->isBooted()) {
            return;
        }

        $this->shutdownProfile = $this->initProfiler();

        $this->profile($this->shutdownProfile, 'shutdown', function () {
            $this->hooks->invokeOnShutdownCallbacks($this);

            $this->container = null;
            $this->storage   = [];

            $this->resetter->gc();
        });

        $this->shutdown = true;

        $this->profile($this->shutdownProfile, 'afterShutdown', function () {
            $this->hooks->invokeAfterShutdownCallbacks($this);
        });
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
    public function getEnvironment(): EnvironmentInterface
    {
        return $this->environment;
    }

    /**
     * @inheritdoc
     */
    public function onBooting(callable $callback): static
    {
        KernelException::throwIf($this->isBooted(), 'Kernel has already been booted, cannot add new pre-boot callbacks');

        $this->hooks->onBooting($callback);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function onBooted(callable $callback): static
    {
        KernelException::throwIf($this->isBooted(), 'Kernel has already been booted, cannot add new post-boot callbacks');

        $this->hooks->onBooted($callback);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function onShutdown(callable $callback): static
    {
        KernelException::throwIf($this->isShutdown(), 'Kernel has already been shutdown, cannot add new pre-shutdown callbacks');

        $this->hooks->onShutdown($callback);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function afterShutdown(callable $callback): static
    {
        KernelException::throwIf($this->isShutdown(), 'Kernel has already been shutdown, cannot add new post-shutdown callbacks');

        $this->hooks->afterShutdown($callback);

        return $this;
    }

    public function onResolving(callable $callback): static
    {
        KernelException::throwIf($this->isBooted(), 'Kernel has already been booted, cannot add new pre-resolution callbacks');

        $this->builder->onResolving($callback);

        return $this;
    }

    public function onResolved(callable $callback): static
    {
        KernelException::throwIf($this->isBooted(), 'Kernel has already been booted, cannot add new post-resolution callbacks');

        $this->builder->onResolved($callback);

        return $this;
    }

    public function define(string $id, callable $factory): DefinitionInterface
    {
        KernelException::throwIf($this->isBooted(), 'Kernel has already been booted, cannot add new container definitions');

        return $this->definitions->add($id, $factory);
    }

    public function defineFallback(string $id, callable $factory): DefinitionInterface
    {
        KernelException::throwIf($this->isBooted(), 'Kernel has already been booted, cannot add new definition fallbacks');

        return $this->definitions->addFallback($id, $factory);
    }

    public function decorate(string $id, callable $decorator): static
    {
        KernelException::throwIf($this->isBooted(), 'Kernel has already been booted, cannot add new definition decorators');

        $this->definitions->decorate($id, $decorator);

        return $this;
    }

    public function override(string $id, callable $factory, bool $preserve = false): DefinitionInterface
    {
        KernelException::throwIf($this->isBooted(), 'Kernel has already been booted, cannot override service definitions');

        return $this->definitions->override($id, $factory, $preserve);
    }

    public function resetServices(int $failureThreshold = 3): static
    {
        KernelException::throwIfNot($this->isBooted(), 'Kernel has not been booted, cannot reset shared services');

        KernelException::throwIf($this->isShutdown(), 'Kernel is shutdown, cannot reset shared services');

        $this->resetter->reset($failureThreshold);

        return $this;
    }

    public function addModule(ModuleInterface $module): static
    {
        KernelException::throwIf($this->isBooted(), 'Kernel has already been booted, cannot add new modules');

        KernelException::throwIf($this->isBooting(), 'Cannot add modules after the kernel has started booting');

        $this->modules->add($module);

        return $this;
    }

    public function getModules(): array
    {
        return $this->modules->getModules();
    }

    public function getContainer(): ContainerInterface
    {
        KernelException::throwIfNot($this->isBooted(), 'Container is inaccessible, kernel has not been booted');

        KernelException::throwIf($this->isShutdown(), 'Container is inaccessible, kernel is shutdown');

        assert(null !== $this->container);

        return $this->container;
    }

    public function getStartTime(): float
    {
        return $this->bootProfile?->getStartTime() ?? -INF;
    }

    public function getDebugInfo(): array
    {
        if (!$this->isDebug()) {
            return [];
        }

        $info = [];

        if ($this->bootProfile !== null) {
            $info['boot.profile'] = $this->bootProfile->getDebugInfo();
            $info['modules']      = $this->modules->getDebugInfo();
        }

        if (null !== $this->serviceResolution) {
            $info['services'] = $this->serviceResolution->getDebugInfo();
            $info['service.resetter'] = $this->resetter->getDebugInfo();
        }

        if (null !== $this->shutdownProfile) {
            $info['shutdown.profile'] = $this->shutdownProfile->getDebugInfo();
        }

        return $info;
    }
}
