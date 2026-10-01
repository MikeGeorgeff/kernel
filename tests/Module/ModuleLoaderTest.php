<?php

namespace Georgeff\Kernel\Test\Module;

use Georgeff\Kernel\Contract\AggregateModuleInterface;
use Georgeff\Kernel\Contract\BootableModuleInterface;
use Georgeff\Kernel\Contract\ConfigurableModuleInterface;
use Georgeff\Kernel\Contract\EnvironmentInterface;
use Georgeff\Kernel\Contract\ModuleInterface;
use Georgeff\Kernel\Environment\Production;
use Georgeff\Kernel\Environment\Staging;
use Georgeff\Kernel\Environment\Testing;
use Georgeff\Kernel\Exception\KernelException;
use Georgeff\Kernel\Exception\ModuleException;
use Georgeff\Kernel\KernelInterface;
use Georgeff\Kernel\Module\ModuleLoader;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

class ModuleLoaderTest extends TestCase
{
    // -------------------------------------------------------------------------
    // add()
    // -------------------------------------------------------------------------

    public function test_add_registers_a_module(): void
    {
        $loader = new ModuleLoader();
        $kernel = $this->createStub(KernelInterface::class);

        $registered = false;
        $module = new class($registered) implements ModuleInterface {
            public function __construct(private bool &$registered) {}
            public function register(KernelInterface $kernel): void { $this->registered = true; }
        };

        $loader->add($module);
        $loader->load(new Testing());
        $loader->register($kernel);

        $this->assertTrue($registered);
    }

    public function test_add_throws_on_duplicate_module_class(): void
    {
        $loader = new ModuleLoader();

        $module = $this->createStub(ModuleInterface::class);

        $loader->add($module);

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage(sprintf('Module [%s] has already been added', $module::class));

        $loader->add(clone $module);
    }

    public function test_add_throws_after_load(): void
    {
        $loader = new ModuleLoader();
        $module = $this->createStub(ModuleInterface::class);

        $loader->load(new Testing());

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage('Cannot add modules after modules have been loaded');

        $loader->add($module);
    }

    // -------------------------------------------------------------------------
    // Aggregate modules
    // -------------------------------------------------------------------------

    public function test_aggregate_module_expands_its_modules_on_load(): void
    {
        $loader = new ModuleLoader();
        $kernel = $this->createStub(KernelInterface::class);

        $registered = false;
        $module = new class($registered) implements ModuleInterface {
            public function __construct(private bool &$registered) {}
            public function register(KernelInterface $kernel): void { $this->registered = true; }
        };

        $aggregate = new class($module) implements AggregateModuleInterface {
            public function __construct(private ModuleInterface $module) {}
            public function register(KernelInterface $kernel): void {}
            public function modules(EnvironmentInterface $env): array { return [$this->module]; }
        };

        $loader->add($aggregate);
        $loader->load(new Testing());
        $loader->register($kernel);

        $this->assertTrue($registered);
    }

    public function test_aggregate_module_itself_is_registered(): void
    {
        $loader = new ModuleLoader();
        $kernel = $this->createStub(KernelInterface::class);

        $registered = false;
        $aggregate = new class($registered) implements AggregateModuleInterface {
            public function __construct(private bool &$registered) {}
            public function register(KernelInterface $kernel): void { $this->registered = true; }
            public function modules(EnvironmentInterface $env): array { return []; }
        };

        $loader->add($aggregate);
        $loader->load(new Testing());
        $loader->register($kernel);

        $this->assertTrue($registered);
    }

    public function test_aggregate_module_passes_environment_to_modules(): void
    {
        $loader = new ModuleLoader();
        $capturedEnv = null;
        $environment = new Staging();

        $aggregate = new class($capturedEnv) implements AggregateModuleInterface {
            public function __construct(private mixed &$capturedEnv) {}
            public function register(KernelInterface $kernel): void {}
            public function modules(EnvironmentInterface $env): array
            {
                $this->capturedEnv = $env;
                return [];
            }
        };

        $loader->add($aggregate);
        $loader->load($environment);

        $this->assertSame($environment, $capturedEnv);
    }

    public function test_nested_aggregate_modules_are_expanded(): void
    {
        $loader = new ModuleLoader();
        $kernel = $this->createStub(KernelInterface::class);

        $registered = false;
        $leaf = new class($registered) implements ModuleInterface {
            public function __construct(private bool &$registered) {}
            public function register(KernelInterface $kernel): void { $this->registered = true; }
        };

        $inner = new class($leaf) implements AggregateModuleInterface {
            public function __construct(private ModuleInterface $leaf) {}
            public function register(KernelInterface $kernel): void {}
            public function modules(EnvironmentInterface $env): array { return [$this->leaf]; }
        };

        $outer = new class($inner) implements AggregateModuleInterface {
            public function __construct(private ModuleInterface $inner) {}
            public function register(KernelInterface $kernel): void {}
            public function modules(EnvironmentInterface $env): array { return [$this->inner]; }
        };

        $loader->add($outer);
        $loader->load(new Testing());
        $loader->register($kernel);

        $this->assertTrue($registered);
    }

    /**
     * Every call returns an instance of the same class, so two instances collide on
     * module dedup; register() records the instance's label so tests can tell which one
     * was kept.
     *
     * @param list<string> $log
     */
    private function labelledModule(string $label, array &$log): ModuleInterface
    {
        return new class($label, $log) implements ModuleInterface {
            /** @param list<string> $log */
            public function __construct(private string $label, private array &$log) {}
            public function register(KernelInterface $kernel): void { $this->log[] = $this->label; }
        };
    }

    public function test_aggregate_skips_a_module_already_added_directly(): void
    {
        $loader = new ModuleLoader();
        $kernel = $this->createStub(KernelInterface::class);
        $log    = [];

        $direct     = $this->labelledModule('direct', $log);
        $aggregated = $this->labelledModule('aggregated', $log);

        $aggregate = new class($aggregated) implements AggregateModuleInterface {
            public function __construct(private ModuleInterface $module) {}
            public function register(KernelInterface $kernel): void {}
            public function modules(EnvironmentInterface $env): array { return [$this->module]; }
        };

        $loader->add($direct);
        $loader->add($aggregate);
        $loader->load(new Testing());
        $loader->register($kernel);

        $this->assertSame(['direct'], $log);
        $this->assertSame([$direct::class, $aggregate::class], $loader->getModules());
    }

    public function test_aggregate_keeps_the_directly_added_module_even_when_added_after_the_aggregate(): void
    {
        $loader = new ModuleLoader();
        $kernel = $this->createStub(KernelInterface::class);
        $log    = [];

        $direct     = $this->labelledModule('direct', $log);
        $aggregated = $this->labelledModule('aggregated', $log);

        $aggregate = new class($aggregated) implements AggregateModuleInterface {
            public function __construct(private ModuleInterface $module) {}
            public function register(KernelInterface $kernel): void {}
            public function modules(EnvironmentInterface $env): array { return [$this->module]; }
        };

        // Aggregates expand during load(), after every direct add, so registration
        // order between the two direct add() calls doesn't change which instance wins.
        $loader->add($aggregate);
        $loader->add($direct);
        $loader->load(new Testing());
        $loader->register($kernel);

        $this->assertSame(['direct'], $log);
    }

    public function test_two_aggregates_sharing_a_module_register_it_once(): void
    {
        $loader = new ModuleLoader();
        $kernel = $this->createStub(KernelInterface::class);
        $log    = [];

        $first = new class($this->labelledModule('first', $log)) implements AggregateModuleInterface {
            public function __construct(private ModuleInterface $module) {}
            public function register(KernelInterface $kernel): void {}
            public function modules(EnvironmentInterface $env): array { return [$this->module]; }
        };

        $second = new class($this->labelledModule('second', $log)) implements AggregateModuleInterface {
            public function __construct(private ModuleInterface $module) {}
            public function register(KernelInterface $kernel): void {}
            public function modules(EnvironmentInterface $env): array { return [$this->module]; }
        };

        $loader->add($first);
        $loader->add($second);
        $loader->load(new Testing());
        $loader->register($kernel);

        $this->assertSame(['first'], $log);
        $this->assertCount(3, $loader->getModules());
    }

    public function test_a_nested_aggregate_shared_by_two_aggregates_is_expanded_once(): void
    {
        $loader    = new ModuleLoader();
        $kernel    = $this->createStub(KernelInterface::class);
        $log       = [];
        $expanded  = 0;

        $makeInner = function () use (&$log, &$expanded): AggregateModuleInterface {
            return new class($this->labelledModule('leaf', $log), $expanded) implements AggregateModuleInterface {
                public function __construct(private ModuleInterface $leaf, private int &$expanded) {}
                public function register(KernelInterface $kernel): void {}
                public function modules(EnvironmentInterface $env): array
                {
                    $this->expanded++;

                    return [$this->leaf];
                }
            };
        };

        $first = new class($makeInner()) implements AggregateModuleInterface {
            public function __construct(private AggregateModuleInterface $inner) {}
            public function register(KernelInterface $kernel): void {}
            public function modules(EnvironmentInterface $env): array { return [$this->inner]; }
        };

        $second = new class($makeInner()) implements AggregateModuleInterface {
            public function __construct(private AggregateModuleInterface $inner) {}
            public function register(KernelInterface $kernel): void {}
            public function modules(EnvironmentInterface $env): array { return [$this->inner]; }
        };

        $loader->add($first);
        $loader->add($second);
        $loader->load(new Testing());
        $loader->register($kernel);

        $this->assertSame(1, $expanded);
        $this->assertSame(['leaf'], $log);
    }

    public function test_aggregate_cycle_terminates_and_registers_each_module_once(): void
    {
        $loader = new ModuleLoader();

        // $a and $b return each other, forming a cycle. Expansion skips a module that's
        // already added without recursing into it, so the cycle ends after one pass.
        $a = new class implements AggregateModuleInterface {
            public ?AggregateModuleInterface $other = null;
            public function register(KernelInterface $kernel): void {}
            public function modules(EnvironmentInterface $env): array { return [$this->other]; }
        };

        $b = new class($a) implements AggregateModuleInterface {
            public function __construct(private AggregateModuleInterface $other) {}
            public function register(KernelInterface $kernel): void {}
            public function modules(EnvironmentInterface $env): array { return [$this->other]; }
        };

        $a->other = $b;

        $loader->add($a);
        $loader->load(new Testing());

        $this->assertSame([$a::class, $b::class], $loader->getModules());
    }

    public function test_aggregate_modules_contribute_config(): void
    {
        $loader = new ModuleLoader();

        $configurable = new class implements ConfigurableModuleInterface {
            public function register(KernelInterface $kernel): void {}
            public function config(EnvironmentInterface $env): array { return ['db.host' => 'localhost']; }
        };

        $aggregate = new class($configurable) implements AggregateModuleInterface {
            public function __construct(private ModuleInterface $module) {}
            public function register(KernelInterface $kernel): void {}
            public function modules(EnvironmentInterface $env): array { return [$this->module]; }
        };

        $loader->add($aggregate);

        $config = $loader->load(new Testing());

        $this->assertSame(['db.host' => 'localhost'], $config);
    }

    // -------------------------------------------------------------------------
    // load()
    // -------------------------------------------------------------------------

    public function test_load_returns_empty_array_with_no_configurable_modules(): void
    {
        $loader = new ModuleLoader();
        $loader->add($this->createStub(ModuleInterface::class));

        $config = $loader->load(new Testing());

        $this->assertSame([], $config);
    }

    public function test_load_returns_merged_config_from_configurable_modules(): void
    {
        $loader = new ModuleLoader();

        $moduleA = new class implements ConfigurableModuleInterface {
            public function register(KernelInterface $kernel): void {}
            public function config(EnvironmentInterface $env): array { return ['db.host' => 'localhost', 'db.port' => 3306]; }
        };

        $moduleB = new class implements ConfigurableModuleInterface {
            public function register(KernelInterface $kernel): void {}
            public function config(EnvironmentInterface $env): array { return ['cache.driver' => 'redis']; }
        };

        $loader->add($moduleA);
        $loader->add($moduleB);

        $config = $loader->load(new Testing());

        $this->assertSame([
            'db.host'      => 'localhost',
            'db.port'      => 3306,
            'cache.driver' => 'redis',
        ], $config);
    }

    public function test_load_last_defined_config_key_wins(): void
    {
        $loader = new ModuleLoader();

        $moduleA = new class implements ConfigurableModuleInterface {
            public function register(KernelInterface $kernel): void {}
            public function config(EnvironmentInterface $env): array { return ['db.host' => 'localhost']; }
        };

        $moduleB = new class implements ConfigurableModuleInterface {
            public function register(KernelInterface $kernel): void {}
            public function config(EnvironmentInterface $env): array { return ['db.host' => 'production.host']; }
        };

        $loader->add($moduleA);
        $loader->add($moduleB);

        $config = $loader->load(new Testing());

        $this->assertSame('production.host', $config['db.host']);
    }

    public function test_load_passes_environment_to_configurable_modules(): void
    {
        $loader = new ModuleLoader();
        $capturedEnv = null;
        $environment = new Production();

        $module = new class($capturedEnv) implements ConfigurableModuleInterface {
            public function __construct(private mixed &$capturedEnv) {}
            public function register(KernelInterface $kernel): void {}
            public function config(EnvironmentInterface $env): array
            {
                $this->capturedEnv = $env;
                return [];
            }
        };

        $loader->add($module);
        $loader->load($environment);

        $this->assertSame($environment, $capturedEnv);
    }

    public function test_load_is_idempotent(): void
    {
        $loader = new ModuleLoader();

        $callCount = 0;
        $module = new class($callCount) implements ConfigurableModuleInterface {
            public function __construct(private int &$callCount) {}
            public function register(KernelInterface $kernel): void {}
            public function config(EnvironmentInterface $env): array
            {
                $this->callCount++;
                return ['key' => 'value'];
            }
        };

        $loader->add($module);

        $first  = $loader->load(new Testing());
        $second = $loader->load(new Testing());

        $this->assertSame($first, $second);
        $this->assertSame(1, $callCount);
    }

    // -------------------------------------------------------------------------
    // register()
    // -------------------------------------------------------------------------

    public function test_register_calls_register_on_all_modules(): void
    {
        $loader = new ModuleLoader();

        $callCount = 0;
        $kernel = $this->createStub(KernelInterface::class);

        // Two separate anonymous class definitions produce distinct ::class names
        $moduleA = new class($callCount) implements ModuleInterface {
            public function __construct(private int &$callCount) {}
            public function register(KernelInterface $kernel): void { $this->callCount++; }
        };

        $moduleB = new class($callCount) implements ModuleInterface {
            public function __construct(private int &$callCount) {}
            public function register(KernelInterface $kernel): void { $this->callCount++; }
        };

        $loader->add($moduleA);
        $loader->add($moduleB);
        $loader->load(new Testing());
        $loader->register($kernel);

        $this->assertSame(2, $callCount);
    }

    public function test_register_is_idempotent(): void
    {
        $loader = new ModuleLoader();

        $callCount = 0;
        $kernel = $this->createStub(KernelInterface::class);

        $module = new class($callCount) implements ModuleInterface {
            public function __construct(private int &$callCount) {}
            public function register(KernelInterface $kernel): void { $this->callCount++; }
        };

        $loader->add($module);
        $loader->load(new Testing());
        $loader->register($kernel);
        $loader->register($kernel);

        $this->assertSame(1, $callCount);
    }

    public function test_register_throws_if_not_loaded(): void
    {
        $loader = new ModuleLoader();
        $kernel = $this->createStub(KernelInterface::class);

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage('Modules need to be loaded before they can be registered');

        $loader->register($kernel);
    }

    public function test_register_rethrows_kernel_exception_unchanged(): void
    {
        $loader = new ModuleLoader();
        $kernel = $this->createStub(KernelInterface::class);

        $module = new class implements ModuleInterface {
            public function register(KernelInterface $kernel): void
            {
                throw new KernelException('boom');
            }
        };

        $loader->add($module);
        $loader->load(new Testing());

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('boom');

        $loader->register($kernel);
    }

    public function test_register_rethrows_module_exception_unchanged(): void
    {
        $loader = new ModuleLoader();
        $kernel = $this->createStub(KernelInterface::class);

        $module = new class implements ModuleInterface {
            public function register(KernelInterface $kernel): void
            {
                throw new ModuleException('boom');
            }
        };

        $loader->add($module);
        $loader->load(new Testing());

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage('boom');

        $loader->register($kernel);
    }

    public function test_register_rethrows_container_exception_unchanged(): void
    {
        $loader = new ModuleLoader();
        $kernel = $this->createStub(KernelInterface::class);

        $containerException = new class('boom') extends \Exception implements ContainerExceptionInterface {};

        $module = new class($containerException) implements ModuleInterface {
            public function __construct(private \Throwable $exception) {}
            public function register(KernelInterface $kernel): void
            {
                throw $this->exception;
            }
        };

        $loader->add($module);
        $loader->load(new Testing());

        $this->expectException(ContainerExceptionInterface::class);
        $this->expectExceptionMessage('boom');

        $loader->register($kernel);
    }

    public function test_register_wraps_unexpected_throwable_in_module_exception(): void
    {
        $loader = new ModuleLoader();
        $kernel = $this->createStub(KernelInterface::class);

        $module = new class implements ModuleInterface {
            public function register(KernelInterface $kernel): void
            {
                throw new \RuntimeException('boom');
            }
        };

        $loader->add($module);
        $loader->load(new Testing());

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage(sprintf('Failed to register module [%s]: boom', $module::class));

        $loader->register($kernel);
    }

    public function test_register_wrapped_exception_preserves_the_original_as_previous(): void
    {
        $loader = new ModuleLoader();
        $kernel = $this->createStub(KernelInterface::class);

        $original = new \RuntimeException('boom');

        $module = new class($original) implements ModuleInterface {
            public function __construct(private \Throwable $exception) {}
            public function register(KernelInterface $kernel): void
            {
                throw $this->exception;
            }
        };

        $loader->add($module);
        $loader->load(new Testing());

        try {
            $loader->register($kernel);
            $this->fail('Expected ModuleException was not thrown');
        } catch (ModuleException $e) {
            $this->assertSame($original, $e->getPrevious());
        }
    }

    // -------------------------------------------------------------------------
    // boot()
    // -------------------------------------------------------------------------

    public function test_boot_calls_boot_on_bootable_modules(): void
    {
        $loader = new ModuleLoader();
        $kernel = $this->createStub(KernelInterface::class);
        $container = $this->createStub(ContainerInterface::class);

        $booted = false;
        $module = new class($booted) implements BootableModuleInterface {
            public function __construct(private bool &$booted) {}
            public function register(KernelInterface $kernel): void {}
            public function boot(ContainerInterface $container): void { $this->booted = true; }
        };

        $loader->add($module);
        $loader->load(new Testing());
        $loader->register($kernel);
        $loader->boot($container);

        $this->assertTrue($booted);
    }

    public function test_boot_skips_non_bootable_modules(): void
    {
        $loader = new ModuleLoader();
        $kernel = $this->createStub(KernelInterface::class);
        $container = $this->createStub(ContainerInterface::class);

        $module = $this->createMock(ModuleInterface::class);
        $module->expects($this->once())->method('register');

        $loader->add($module);
        $loader->load(new Testing());
        $loader->register($kernel);
        $loader->boot($container);
    }

    public function test_boot_passes_container_to_bootable_modules(): void
    {
        $loader = new ModuleLoader();
        $kernel = $this->createStub(KernelInterface::class);
        $container = $this->createStub(ContainerInterface::class);

        $capturedContainer = null;
        $module = new class($capturedContainer) implements BootableModuleInterface {
            public function __construct(private mixed &$capturedContainer) {}
            public function register(KernelInterface $kernel): void {}
            public function boot(ContainerInterface $container): void { $this->capturedContainer = $container; }
        };

        $loader->add($module);
        $loader->load(new Testing());
        $loader->register($kernel);
        $loader->boot($container);

        $this->assertSame($container, $capturedContainer);
    }

    public function test_boot_is_idempotent(): void
    {
        $loader = new ModuleLoader();
        $kernel = $this->createStub(KernelInterface::class);
        $container = $this->createStub(ContainerInterface::class);

        $callCount = 0;
        $module = new class($callCount) implements BootableModuleInterface {
            public function __construct(private int &$callCount) {}
            public function register(KernelInterface $kernel): void {}
            public function boot(ContainerInterface $container): void { $this->callCount++; }
        };

        $loader->add($module);
        $loader->load(new Testing());
        $loader->register($kernel);
        $loader->boot($container);
        $loader->boot($container);

        $this->assertSame(1, $callCount);
    }

    public function test_boot_throws_if_not_registered(): void
    {
        $loader = new ModuleLoader();
        $container = $this->createStub(ContainerInterface::class);

        $loader->load(new Testing());

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage('Modules need to be registered before they can be booted');

        $loader->boot($container);
    }

    public function test_boot_rethrows_kernel_exception_unchanged(): void
    {
        $loader = new ModuleLoader();
        $kernel = $this->createStub(KernelInterface::class);
        $container = $this->createStub(ContainerInterface::class);

        $module = new class implements BootableModuleInterface {
            public function register(KernelInterface $kernel): void {}
            public function boot(ContainerInterface $container): void
            {
                throw new KernelException('boom');
            }
        };

        $loader->add($module);
        $loader->load(new Testing());
        $loader->register($kernel);

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('boom');

        $loader->boot($container);
    }

    public function test_boot_rethrows_module_exception_unchanged(): void
    {
        $loader = new ModuleLoader();
        $kernel = $this->createStub(KernelInterface::class);
        $container = $this->createStub(ContainerInterface::class);

        $module = new class implements BootableModuleInterface {
            public function register(KernelInterface $kernel): void {}
            public function boot(ContainerInterface $container): void
            {
                throw new ModuleException('boom');
            }
        };

        $loader->add($module);
        $loader->load(new Testing());
        $loader->register($kernel);

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage('boom');

        $loader->boot($container);
    }

    public function test_boot_rethrows_container_exception_unchanged(): void
    {
        $loader = new ModuleLoader();
        $kernel = $this->createStub(KernelInterface::class);
        $container = $this->createStub(ContainerInterface::class);

        $containerException = new class('boom') extends \Exception implements ContainerExceptionInterface {};

        $module = new class($containerException) implements BootableModuleInterface {
            public function __construct(private \Throwable $exception) {}
            public function register(KernelInterface $kernel): void {}
            public function boot(ContainerInterface $container): void
            {
                throw $this->exception;
            }
        };

        $loader->add($module);
        $loader->load(new Testing());
        $loader->register($kernel);

        $this->expectException(ContainerExceptionInterface::class);
        $this->expectExceptionMessage('boom');

        $loader->boot($container);
    }

    public function test_boot_wraps_unexpected_throwable_in_module_exception(): void
    {
        $loader = new ModuleLoader();
        $kernel = $this->createStub(KernelInterface::class);
        $container = $this->createStub(ContainerInterface::class);

        $module = new class implements BootableModuleInterface {
            public function register(KernelInterface $kernel): void {}
            public function boot(ContainerInterface $container): void
            {
                throw new \RuntimeException('boom');
            }
        };

        $loader->add($module);
        $loader->load(new Testing());
        $loader->register($kernel);

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage(sprintf('Failed to boot module [%s]: boom', $module::class));

        $loader->boot($container);
    }

    public function test_boot_wrapped_exception_preserves_the_original_as_previous(): void
    {
        $loader = new ModuleLoader();
        $kernel = $this->createStub(KernelInterface::class);
        $container = $this->createStub(ContainerInterface::class);

        $original = new \RuntimeException('boom');

        $module = new class($original) implements BootableModuleInterface {
            public function __construct(private \Throwable $exception) {}
            public function register(KernelInterface $kernel): void {}
            public function boot(ContainerInterface $container): void
            {
                throw $this->exception;
            }
        };

        $loader->add($module);
        $loader->load(new Testing());
        $loader->register($kernel);

        try {
            $loader->boot($container);
            $this->fail('Expected ModuleException was not thrown');
        } catch (ModuleException $e) {
            $this->assertSame($original, $e->getPrevious());
        }
    }

    // -------------------------------------------------------------------------
    // getModules()
    // -------------------------------------------------------------------------

    public function test_get_modules_returns_an_empty_list_initially(): void
    {
        $loader = new ModuleLoader();

        $this->assertSame([], $loader->getModules());
    }

    public function test_get_modules_reflects_a_module_immediately_after_add_before_load(): void
    {
        $loader = new ModuleLoader();
        $module = $this->createStub(ModuleInterface::class);

        $loader->add($module);

        $this->assertSame([$module::class], $loader->getModules());
    }

    public function test_get_modules_includes_aggregate_expanded_modules_after_load(): void
    {
        $loader = new ModuleLoader();

        $module = $this->createStub(ModuleInterface::class);

        $aggregate = new class($module) implements AggregateModuleInterface {
            public function __construct(private ModuleInterface $module) {}
            public function register(KernelInterface $kernel): void {}
            public function modules(EnvironmentInterface $env): array { return [$this->module]; }
        };

        $loader->add($aggregate);

        $this->assertSame([$aggregate::class], $loader->getModules(), 'Aggregate children should not be expanded before load()');

        $loader->load(new Testing());

        $this->assertSame([$aggregate::class, $module::class], $loader->getModules());
    }

    public function test_get_modules_does_not_include_a_module_rejected_by_the_duplicate_guard(): void
    {
        $loader = new ModuleLoader();
        $module = $this->createStub(ModuleInterface::class);

        $loader->add($module);

        try {
            $loader->add(clone $module);
        } catch (ModuleException) {
        }

        $this->assertSame([$module::class], $loader->getModules());
    }

    // -------------------------------------------------------------------------
    // getDebugInfo()
    // -------------------------------------------------------------------------

    public function test_get_debug_info_reflects_initial_state(): void
    {
        $loader = new ModuleLoader();

        $info = $loader->getDebugInfo();

        $this->assertFalse($info['loaded']);
        $this->assertFalse($info['registered']);
        $this->assertFalse($info['booted']);
        $this->assertSame([], $info['modules']);
    }

    public function test_get_debug_info_reflects_state_after_each_phase(): void
    {
        $loader = new ModuleLoader();
        $kernel = $this->createStub(KernelInterface::class);
        $container = $this->createStub(ContainerInterface::class);

        $module = $this->createStub(ModuleInterface::class);
        $loader->add($module);

        $loader->load(new Testing());
        $info = $loader->getDebugInfo();
        $this->assertTrue($info['loaded']);
        $this->assertFalse($info['registered']);
        $this->assertFalse($info['booted']);
        $this->assertContains($module::class, $info['modules']);

        $loader->register($kernel);
        $info = $loader->getDebugInfo();
        $this->assertTrue($info['registered']);
        $this->assertFalse($info['booted']);

        $loader->boot($container);
        $info = $loader->getDebugInfo();
        $this->assertTrue($info['booted']);
    }
}
