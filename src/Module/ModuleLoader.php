<?php

namespace Georgeff\Kernel\Module;

use Georgeff\Kernel\Environment;
use Georgeff\Kernel\KernelInterface;
use Psr\Container\ContainerInterface;
use Georgeff\Kernel\Exception\KernelException;
use Georgeff\Kernel\Debug\DebuggableInterface;

/**
 * @internal
 */
final class ModuleLoader implements DebuggableInterface
{
    /**
     * @var array<string, ModuleInterface>
     */
    private array $modules = [];

    /**
     * @var array<string, ModuleRepositoryInterface>
     */
    private array $repositories = [];

    /**
     * @var array<string, mixed>
     */
    private array $config = [];

    /**
     * Indicates if the modules have already been loaded
     */
    private bool $loaded = false;

    /**
     * Indicates if the registration phase has been run
     */
    private bool $registered = false;

    /**
     * Indicates if the modules have been booted
     */
    private bool $booted = false;

    public function add(ModuleInterface $module): void
    {
        if ($this->loaded) {
            throw new \LogicException('Cannot add modules after modules have been loaded');
        }

        if (isset($this->modules[$module::class])) {
            throw new KernelException(sprintf('Module "%s" has already been added', $module::class));
        }

        $this->modules[$module::class] = $module;
    }

    public function addRepository(ModuleRepositoryInterface $repository): void
    {
        if ($this->loaded) {
            throw new \LogicException('Cannot add module repositories after modules have been loaded');
        }

        if (isset($this->repositories[$repository::class])) {
            throw new KernelException(sprintf('Module repository "%s" has already been added', $repository::class));
        }

        $this->repositories[$repository::class] = $repository;
    }

    /**
     * Flatten modules from repos, merge and return the config
     *
     * @return array<string, mixed>
     */
    public function load(Environment $env): array
    {
        if ($this->loaded) {
            return $this->config;
        }

        $config = [];

        $this->loadModulesFromRepositories($env);

        foreach ($this->modules as $module) {
            if ($module instanceof ConfigurableModuleInterface) {
                $config = array_merge($config, $module->config($env));
            }
        }

        $this->loaded = true;

        return $this->config = $config;
    }

    public function register(KernelInterface $kernel): void
    {
        if ($this->registered) {
            return;
        }

        if (!$this->loaded) {
            throw new \LogicException('Modules need to be loaded before they can be registered');
        }

        foreach ($this->modules as $module) {
            $module->register($kernel);
        }

        $this->registered = true;
    }

    public function boot(ContainerInterface $container): void
    {
        if ($this->booted) {
            return;
        }

        if (!$this->registered) {
            throw new \LogicException('Modules need to be registered before they can be booted');
        }

        foreach ($this->modules as $module) {
            if ($module instanceof BootableModuleInterface) {
                $module->boot($container);
            }
        }

        $this->booted = true;
    }

    private function loadModulesFromRepositories(Environment $env): void
    {
        foreach ($this->repositories as $repo) {
            foreach ($repo->modules($env) as $module) {
                $this->add($module);
            }
        }

        $this->repositories = [];
    }

    public function getDebugInfo(): array
    {
        return [
            'loaded'     => $this->loaded,
            'registered' => $this->registered,
            'booted'     => $this->booted,
            'modules'    => array_keys($this->modules),
        ];
    }
}
