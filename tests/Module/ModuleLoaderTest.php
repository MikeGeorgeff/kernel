<?php

namespace Georgeff\Kernel\Test\Module;

use Georgeff\Kernel\Environment;
use Georgeff\Kernel\Exception\KernelException;
use Georgeff\Kernel\KernelInterface;
use Georgeff\Kernel\Module\BootableModuleInterface;
use Georgeff\Kernel\Module\ConfigurableModuleInterface;
use Georgeff\Kernel\Module\ModuleInterface;
use Georgeff\Kernel\Module\ModuleLoader;
use Georgeff\Kernel\Module\ModuleRepositoryInterface;
use PHPUnit\Framework\TestCase;
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
        $loader->load(Environment::Testing);
        $loader->register($kernel);

        $this->assertTrue($registered);
    }

    public function test_add_throws_on_duplicate_module_class(): void
    {
        $loader = new ModuleLoader();

        $module = $this->createStub(ModuleInterface::class);

        $loader->add($module);

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage(sprintf('Module "%s" has already been added', $module::class));

        $loader->add(clone $module);
    }

    public function test_add_throws_after_load(): void
    {
        $loader = new ModuleLoader();
        $module = $this->createStub(ModuleInterface::class);

        $loader->load(Environment::Testing);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot add modules after modules have been loaded');

        $loader->add($module);
    }

    // -------------------------------------------------------------------------
    // addRepository()
    // -------------------------------------------------------------------------

    public function test_add_repository_flattens_modules_on_load(): void
    {
        $loader = new ModuleLoader();
        $kernel = $this->createStub(KernelInterface::class);

        $registered = false;
        $module = new class($registered) implements ModuleInterface {
            public function __construct(private bool &$registered) {}
            public function register(KernelInterface $kernel): void { $this->registered = true; }
        };

        $repo = new class($module) implements ModuleRepositoryInterface {
            public function __construct(private ModuleInterface $module) {}
            public function modules(Environment $env): array { return [$this->module]; }
        };

        $loader->addRepository($repo);
        $loader->load(Environment::Testing);
        $loader->register($kernel);

        $this->assertTrue($registered);
    }

    public function test_add_repository_passes_environment_to_modules(): void
    {
        $loader = new ModuleLoader();
        $capturedEnv = null;

        $repo = new class($capturedEnv) implements ModuleRepositoryInterface {
            public function __construct(private mixed &$capturedEnv) {}
            public function modules(Environment $env): array
            {
                $this->capturedEnv = $env;
                return [];
            }
        };

        $loader->addRepository($repo);
        $loader->load(Environment::Staging);

        $this->assertSame(Environment::Staging, $capturedEnv);
    }

    public function test_add_repository_throws_on_duplicate_repository_class(): void
    {
        $loader = new ModuleLoader();

        $repo = $this->createStub(ModuleRepositoryInterface::class);

        $loader->addRepository($repo);

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage(sprintf('Module repository "%s" has already been added', $repo::class));

        $loader->addRepository(clone $repo);
    }

    public function test_repository_throws_if_module_already_directly_added(): void
    {
        $loader = new ModuleLoader();
        $module = $this->createStub(ModuleInterface::class);

        $repo = new class($module) implements ModuleRepositoryInterface {
            public function __construct(private ModuleInterface $module) {}
            public function modules(Environment $env): array { return [$this->module]; }
        };

        $loader->add($module);
        $loader->addRepository($repo);

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage(sprintf('Module "%s" has already been added', $module::class));

        $loader->load(Environment::Testing);
    }

    public function test_repository_throws_if_two_repos_return_same_module_class(): void
    {
        $loader = new ModuleLoader();
        $module = $this->createStub(ModuleInterface::class);

        $repoA = new class($module) implements ModuleRepositoryInterface {
            public function __construct(private ModuleInterface $module) {}
            public function modules(Environment $env): array { return [$this->module]; }
        };

        $repoB = new class($module) implements ModuleRepositoryInterface {
            public function __construct(private ModuleInterface $module) {}
            public function modules(Environment $env): array { return [clone $this->module]; }
        };

        $loader->addRepository($repoA);
        $loader->addRepository($repoB);

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage(sprintf('Module "%s" has already been added', $module::class));

        $loader->load(Environment::Testing);
    }

    public function test_add_repository_throws_after_load(): void
    {
        $loader = new ModuleLoader();
        $repo = $this->createStub(ModuleRepositoryInterface::class);

        $loader->load(Environment::Testing);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot add module repositories after modules have been loaded');

        $loader->addRepository($repo);
    }

    // -------------------------------------------------------------------------
    // load()
    // -------------------------------------------------------------------------

    public function test_load_returns_empty_array_with_no_configurable_modules(): void
    {
        $loader = new ModuleLoader();
        $loader->add($this->createStub(ModuleInterface::class));

        $config = $loader->load(Environment::Testing);

        $this->assertSame([], $config);
    }

    public function test_load_returns_merged_config_from_configurable_modules(): void
    {
        $loader = new ModuleLoader();

        $moduleA = new class implements ConfigurableModuleInterface {
            public function register(KernelInterface $kernel): void {}
            public function config(Environment $env): array { return ['db.host' => 'localhost', 'db.port' => 3306]; }
        };

        $moduleB = new class implements ConfigurableModuleInterface {
            public function register(KernelInterface $kernel): void {}
            public function config(Environment $env): array { return ['cache.driver' => 'redis']; }
        };

        $loader->add($moduleA);
        $loader->add($moduleB);

        $config = $loader->load(Environment::Testing);

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
            public function config(Environment $env): array { return ['db.host' => 'localhost']; }
        };

        $moduleB = new class implements ConfigurableModuleInterface {
            public function register(KernelInterface $kernel): void {}
            public function config(Environment $env): array { return ['db.host' => 'production.host']; }
        };

        $loader->add($moduleA);
        $loader->add($moduleB);

        $config = $loader->load(Environment::Testing);

        $this->assertSame('production.host', $config['db.host']);
    }

    public function test_load_passes_environment_to_configurable_modules(): void
    {
        $loader = new ModuleLoader();
        $capturedEnv = null;

        $module = new class($capturedEnv) implements ConfigurableModuleInterface {
            public function __construct(private mixed &$capturedEnv) {}
            public function register(KernelInterface $kernel): void {}
            public function config(Environment $env): array
            {
                $this->capturedEnv = $env;
                return [];
            }
        };

        $loader->add($module);
        $loader->load(Environment::Production);

        $this->assertSame(Environment::Production, $capturedEnv);
    }

    public function test_load_is_idempotent(): void
    {
        $loader = new ModuleLoader();

        $callCount = 0;
        $module = new class($callCount) implements ConfigurableModuleInterface {
            public function __construct(private int &$callCount) {}
            public function register(KernelInterface $kernel): void {}
            public function config(Environment $env): array
            {
                $this->callCount++;
                return ['key' => 'value'];
            }
        };

        $loader->add($module);

        $first  = $loader->load(Environment::Testing);
        $second = $loader->load(Environment::Testing);

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
        $loader->load(Environment::Testing);
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
        $loader->load(Environment::Testing);
        $loader->register($kernel);
        $loader->register($kernel);

        $this->assertSame(1, $callCount);
    }

    public function test_register_throws_if_not_loaded(): void
    {
        $loader = new ModuleLoader();
        $kernel = $this->createStub(KernelInterface::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Modules need to be loaded before they can be registered');

        $loader->register($kernel);
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
        $loader->load(Environment::Testing);
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
        $loader->load(Environment::Testing);
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
        $loader->load(Environment::Testing);
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
        $loader->load(Environment::Testing);
        $loader->register($kernel);
        $loader->boot($container);
        $loader->boot($container);

        $this->assertSame(1, $callCount);
    }

    public function test_boot_throws_if_not_registered(): void
    {
        $loader = new ModuleLoader();
        $container = $this->createStub(ContainerInterface::class);

        $loader->load(Environment::Testing);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Modules need to be registered before they can be booted');

        $loader->boot($container);
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

        $loader->load(Environment::Testing);
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
