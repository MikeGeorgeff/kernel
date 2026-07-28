<?php

namespace Georgeff\Kernel\Module;

use Throwable;
use Georgeff\Kernel\KernelInterface;
use Psr\Container\ContainerInterface;
use Georgeff\Kernel\Contract\ModuleInterface;
use Georgeff\Kernel\Exception\ModuleException;
use Psr\Container\ContainerExceptionInterface;
use Georgeff\Kernel\Contract\DebuggableInterface;
use Georgeff\Kernel\Contract\EnvironmentInterface;
use Georgeff\Kernel\Contract\BootableModuleInterface;
use Georgeff\Kernel\Contract\AggregateModuleInterface;
use Georgeff\Kernel\Exception\KernelExceptionInterface;
use Georgeff\Kernel\Contract\ConfigurableModuleInterface;

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
     * @var array<string, AggregateModuleInterface>
     */
    private array $aggregates = [];

    /**
     * @var list<class-string<ModuleInterface>>
     */
    private array $addedModules = [];

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

    public function gc(): void
    {
        $this->modules    = [];
        $this->aggregates = [];
        $this->config     = [];
    }

    /**
     * @return list<class-string<ModuleInterface>>
     */
    public function getModules(): array
    {
        return $this->addedModules;
    }

    public function add(ModuleInterface $module): void
    {
        ModuleException::throwIf($this->loaded, 'Cannot add modules after modules have been loaded');

        ModuleException::throwIf(
            isset($this->modules[$module::class]),
            sprintf('Module [%s] has already been added', $module::class)
        );

        $this->modules[$module::class] = $module;

        $this->addedModules[] = $module::class;

        if ($module instanceof AggregateModuleInterface) {
            $this->aggregates[$module::class] = $module;
        }
    }

    /**
     * Flatten modules from aggregates, merge and return the config
     *
     * @return array<string, mixed>
     */
    public function load(EnvironmentInterface $env): array
    {
        if ($this->loaded) {
            return $this->config;
        }

        $config = [];

        $this->expandAggregates($env);

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

        ModuleException::throwIfNot($this->loaded, 'Modules need to be loaded before they can be registered');

        foreach ($this->modules as $module) {
            try {
                $module->register($kernel);
            } catch (KernelExceptionInterface|ContainerExceptionInterface $e) {
                throw $e;
            } catch (Throwable $e) {
                ModuleException::throwOnRegistrationError($module::class, $e);
            }
        }

        $this->registered = true;
    }

    public function boot(ContainerInterface $container): void
    {
        if ($this->booted) {
            return;
        }

        ModuleException::throwIfNot($this->registered, 'Modules need to be registered before they can be booted');

        foreach ($this->modules as $module) {
            if ($module instanceof BootableModuleInterface) {
                try {
                    $module->boot($container);
                } catch (KernelExceptionInterface|ContainerExceptionInterface $e) {
                    throw $e;
                } catch (Throwable $e) {
                    ModuleException::throwOnBootError($module::class, $e);
                }
            }
        }

        $this->booted = true;
    }

    private function expandAggregates(EnvironmentInterface $env): void
    {
        foreach ($this->aggregates as $aggregate) {
            $this->expandAggregate($aggregate, $env);
        }
    }

    private function expandAggregate(AggregateModuleInterface $aggregate, EnvironmentInterface $env): void
    {
        foreach ($aggregate->modules($env) as $module) {
            $this->add($module);

            if ($module instanceof AggregateModuleInterface) {
                $this->expandAggregate($module, $env);
            }
        }
    }

    public function getDebugInfo(): array
    {
        return [
            'loaded'     => $this->loaded,
            'registered' => $this->registered,
            'booted'     => $this->booted,
            'modules'    => $this->addedModules,
        ];
    }
}
