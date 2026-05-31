<?php

namespace Georgeff\Kernel\Test;

use Georgeff\Kernel\DI\TagRegistryInterface;
use Georgeff\Kernel\Environment;
use Georgeff\Kernel\Event\KernelBooted;
use Georgeff\Kernel\Kernel;
use Georgeff\Kernel\KernelException;
use Georgeff\Kernel\KernelInterface;
use Georgeff\Kernel\Module\BootableModuleInterface;
use Georgeff\Kernel\Module\ConfigurableModuleInterface;
use Georgeff\Kernel\Module\ModuleInterface;
use Georgeff\Kernel\Module\ModuleRepositoryInterface;
use Georgeff\Kernel\ServiceRegistrar;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

class KernelTest extends TestCase
{
    public function test_it_implements_kernel_interface(): void
    {
        $kernel = new Kernel(Environment::Testing);

        $this->assertInstanceOf(KernelInterface::class, $kernel);
    }

    public function test_it_returns_the_environment(): void
    {
        $kernel = new Kernel(Environment::Production);

        $this->assertSame('production', $kernel->getEnvironment());
    }

    public function test_it_returns_each_environment_value(): void
    {
        foreach (Environment::cases() as $env) {
            $kernel = new Kernel($env);

            $this->assertSame($env->value, $kernel->getEnvironment());
        }
    }

    public function test_debug_defaults_to_false(): void
    {
        $kernel = new Kernel(Environment::Testing);

        $this->assertFalse($kernel->isDebug());
    }

    public function test_debug_can_be_enabled(): void
    {
        $kernel = new Kernel(Environment::Testing, debug: true);

        $this->assertTrue($kernel->isDebug());
    }

    public function test_it_is_not_booted_before_boot(): void
    {
        $kernel = new Kernel(Environment::Testing);

        $this->assertFalse($kernel->isBooted());
    }

    public function test_it_boots(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $kernel->boot();

        $this->assertTrue($kernel->isBooted());
    }

    public function test_boot_is_idempotent(): void
    {
        $registrar = $this->createMockRegistrar();

        $registrar->expects($this->once())->method('getContainer');

        $kernel = new Kernel(Environment::Testing, $registrar);
        $kernel->boot();
        $kernel->boot();

        $this->assertTrue($kernel->isBooted());
    }

    public function test_it_returns_the_container_after_boot(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $kernel->boot();

        $this->assertInstanceOf(ContainerInterface::class, $kernel->getContainer());
    }

    public function test_it_throws_when_accessing_container_before_boot(): void
    {
        $kernel = new Kernel(Environment::Testing);

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Container is inaccessible, kernel has not been booted');

        $kernel->getContainer();
    }

    public function test_it_registers_itself_in_the_container(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $kernel->boot();

        $container = $kernel->getContainer();

        $this->assertTrue($container->has('kernel'));
        $this->assertSame($kernel, $container->get('kernel'));
    }

    public function test_it_aliases_itself_as_kernel_interface(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $kernel->boot();

        $container = $kernel->getContainer();

        $this->assertTrue($container->has(KernelInterface::class));
        $this->assertSame($kernel, $container->get(KernelInterface::class));
    }

    public function test_it_registers_user_definitions(): void
    {
        $service = new \stdClass();

        $kernel = new Kernel(Environment::Testing);
        $kernel->addDefinition('my.service', fn() => $service, true);
        $kernel->boot();

        $this->assertSame($service, $kernel->getContainer()->get('my.service'));
    }

    public function test_it_registers_user_definitions_with_aliases(): void
    {
        $service = new \stdClass();

        $kernel = new Kernel(Environment::Testing);
        $kernel->addDefinition('my.service', fn() => $service, true, ['MyServiceAlias']);
        $kernel->boot();

        $container = $kernel->getContainer();

        $this->assertSame($service, $container->get('my.service'));
        $this->assertSame($service, $container->get('MyServiceAlias'));
    }

    public function test_add_definition_returns_the_kernel(): void
    {
        $kernel = new Kernel(Environment::Testing);

        $result = $kernel->addDefinition('foo', fn() => 'bar');

        $this->assertSame($kernel, $result);
    }

    public function test_add_definition_is_fluent(): void
    {
        $kernel = new Kernel(Environment::Testing);

        $kernel
            ->addDefinition('foo', fn() => 'foo_value', true)
            ->addDefinition('bar', fn() => 'bar_value', true);

        $kernel->boot();

        $this->assertSame('foo_value', $kernel->getContainer()->get('foo'));
        $this->assertSame('bar_value', $kernel->getContainer()->get('bar'));
    }

    public function test_add_definition_overwrites_existing_id(): void
    {
        $kernel = new Kernel(Environment::Testing);

        $kernel->addDefinition('foo', fn() => 'first', true);
        $kernel->addDefinition('foo', fn() => 'second', true);
        $kernel->boot();

        $this->assertSame('second', $kernel->getContainer()->get('foo'));
    }

    public function test_it_throws_when_adding_definition_after_boot(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $kernel->boot();

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Kernel has already been booted, cannot add new container definitions');

        $kernel->addDefinition('foo', fn() => 'bar');
    }

    public function test_it_throws_when_adding_definition_with_reserved_kernel_id(): void
    {
        $kernel = new Kernel(Environment::Testing);

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Cannot overwrite a reserved service definition');

        $kernel->addDefinition('kernel', fn() => 'fake');
    }

    public function test_it_throws_when_adding_definition_with_reserved_kernel_interface_id(): void
    {
        $kernel = new Kernel(Environment::Testing);

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Cannot overwrite a reserved service definition');

        $kernel->addDefinition(KernelInterface::class, fn() => 'fake');
    }

    public function test_it_throws_when_adding_definition_with_reserved_kernel_alias(): void
    {
        $kernel = new Kernel(Environment::Testing);

        $this->expectException(KernelException::class);

        $kernel->addDefinition('foo', fn() => 'bar', false, ['kernel']);
    }

    public function test_it_throws_when_adding_definition_with_reserved_kernel_interface_alias(): void
    {
        $kernel = new Kernel(Environment::Testing);

        $this->expectException(KernelException::class);

        $kernel->addDefinition('foo', fn() => 'bar', false, [KernelInterface::class]);
    }

    public function test_on_booting_callback_is_called_during_boot(): void
    {
        $called = false;

        $kernel = new Kernel(Environment::Testing);
        $kernel->onBooting(function (KernelInterface $k) use (&$called) {
            $called = true;
        });

        $kernel->boot();

        $this->assertTrue($called);
    }

    public function test_on_booting_callback_receives_kernel(): void
    {
        $received = null;

        $kernel = new Kernel(Environment::Testing);
        $kernel->onBooting(function (KernelInterface $k) use (&$received) {
            $received = $k;
        });

        $kernel->boot();

        $this->assertSame($kernel, $received);
    }

    public function test_multiple_on_booting_callbacks_are_called_in_order(): void
    {
        $order = [];

        $kernel = new Kernel(Environment::Testing);
        $kernel->onBooting(function () use (&$order) {
            $order[] = 'first';
        });
        $kernel->onBooting(function () use (&$order) {
            $order[] = 'second';
        });

        $kernel->boot();

        $this->assertSame(['first', 'second'], $order);
    }

    public function test_on_booting_returns_the_kernel(): void
    {
        $kernel = new Kernel(Environment::Testing);

        $result = $kernel->onBooting(function () {});

        $this->assertSame($kernel, $result);
    }

    public function test_it_throws_when_registering_on_booting_after_boot(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $kernel->boot();

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Kernel has already been booted, cannot add new pre-boot callbacks');

        $kernel->onBooting(function () {});
    }

    public function test_on_booted_callback_is_called_after_boot(): void
    {
        $called = false;

        $kernel = new Kernel(Environment::Testing);
        $kernel->onBooted(function (KernelInterface $k) use (&$called) {
            $called = true;
        });

        $kernel->boot();

        $this->assertTrue($called);
    }

    public function test_on_booted_callback_receives_kernel(): void
    {
        $received = null;

        $kernel = new Kernel(Environment::Testing);
        $kernel->onBooted(function (KernelInterface $k) use (&$received) {
            $received = $k;
        });

        $kernel->boot();

        $this->assertSame($kernel, $received);
    }

    public function test_on_booted_callback_fires_when_kernel_is_already_booted(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $kernel->onBooted(function (KernelInterface $k) {
            $this->assertTrue($k->isBooted());
        });

        $kernel->boot();
    }

    public function test_multiple_on_booted_callbacks_are_called_in_order(): void
    {
        $order = [];

        $kernel = new Kernel(Environment::Testing);
        $kernel->onBooted(function () use (&$order) {
            $order[] = 'first';
        });
        $kernel->onBooted(function () use (&$order) {
            $order[] = 'second';
        });

        $kernel->boot();

        $this->assertSame(['first', 'second'], $order);
    }

    public function test_on_booted_returns_the_kernel(): void
    {
        $kernel = new Kernel(Environment::Testing);

        $result = $kernel->onBooted(function () {});

        $this->assertSame($kernel, $result);
    }

    public function test_it_throws_when_registering_on_booted_after_boot(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $kernel->boot();

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Kernel has already been booted, cannot add new post-boot callbacks');

        $kernel->onBooted(function () {});
    }

    public function test_on_booting_and_add_definition_are_fluent(): void
    {
        $bootingCalled = false;

        $kernel = new Kernel(Environment::Testing);
        $kernel
            ->onBooting(function () use (&$bootingCalled) {
                $bootingCalled = true;
            })
            ->addDefinition('foo', fn() => 'bar', true);

        $kernel->boot();

        $this->assertTrue($bootingCalled);
        $this->assertSame('bar', $kernel->getContainer()->get('foo'));
    }

    public function test_on_booting_can_add_definitions(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $kernel->onBooting(function (KernelInterface $k): void {
            $k->addDefinition('dynamic', fn() => 'added_in_booting', true);
        });

        $kernel->boot();

        $this->assertSame('added_in_booting', $kernel->getContainer()->get('dynamic'));
    }

    public function test_it_registers_environment_in_container(): void
    {
        $kernel = new Kernel(Environment::Production);
        $kernel->boot();

        $this->assertSame('production', $kernel->getContainer()->get('kernel.environment'));
    }

    public function test_it_registers_debug_in_container(): void
    {
        $kernel = new Kernel(Environment::Testing, debug: true);
        $kernel->boot();

        $this->assertTrue($kernel->getContainer()->get('kernel.debug'));
    }

    public function test_it_registers_debug_false_in_container(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $kernel->boot();

        $this->assertFalse($kernel->getContainer()->get('kernel.debug'));
    }

    public function test_get_start_time_returns_negative_infinity_when_not_debug(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $kernel->boot();

        $this->assertSame(-INF, $kernel->getStartTime());
    }

    public function test_get_start_time_returns_float_when_debug(): void
    {
        $kernel = new Kernel(Environment::Testing, debug: true);
        $kernel->boot();

        $this->assertIsFloat($kernel->getStartTime());
        $this->assertGreaterThan(0, $kernel->getStartTime());
    }

    public function test_get_start_time_returns_negative_infinity_before_boot_without_debug(): void
    {
        $kernel = new Kernel(Environment::Testing);

        $this->assertSame(-INF, $kernel->getStartTime());
    }

    public function test_it_dispatches_kernel_booted_event(): void
    {
        /** @var list<object> $events */
        $events = [];

        $dispatcher = new class($events) implements EventDispatcherInterface {
            /** @param list<object> &$events */
            public function __construct(private array &$events) {}

            public function dispatch(object $event): object
            {
                $this->events[] = $event;

                return $event;
            }
        };

        $kernel = new Kernel(Environment::Testing);
        $kernel->addDefinition(
            EventDispatcherInterface::class,
            fn() => $dispatcher,
            true,
        );
        $kernel->boot();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(KernelBooted::class, $events[0]);
        $this->assertSame($kernel, $events[0]->kernel);
    }

    public function test_boot_does_not_fail_without_event_dispatcher(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $kernel->boot();

        $this->assertTrue($kernel->isBooted());
    }

    public function test_it_uses_default_service_registrar(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $kernel->boot();

        $this->assertInstanceOf(ContainerInterface::class, $kernel->getContainer());
    }

    public function test_it_uses_custom_service_registrar(): void
    {
        $container = $this->createMock(ContainerInterface::class);

        $registrar = $this->createMockRegistrar();
        $registrar->method('getContainer')->willReturn($container);

        $kernel = new Kernel(Environment::Testing, $registrar);
        $kernel->boot();

        $this->assertSame($container, $kernel->getContainer());
    }

    public function test_get_debug_info_returns_empty_array_when_not_debug(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $kernel->boot();

        $this->assertSame([], $kernel->getDebugInfo());
    }

    public function test_get_debug_info_returns_boot_profile_when_debug(): void
    {
        $kernel = new Kernel(Environment::Testing, debug: true);
        $kernel->boot();

        $info = $kernel->getDebugInfo();

        $this->assertArrayHasKey('bootProfile', $info);
        $this->assertArrayHasKey('start.time', $info['bootProfile']);
        $this->assertArrayHasKey('end.time', $info['bootProfile']);
        $this->assertArrayHasKey('duration', $info['bootProfile']);
        $this->assertArrayHasKey('phases', $info['bootProfile']);
        $this->assertArrayHasKey('preBoot', $info['bootProfile']['phases']);
        $this->assertArrayHasKey('serviceRegistration', $info['bootProfile']['phases']);
        $this->assertArrayHasKey('containerInit', $info['bootProfile']['phases']);
    }

    public function test_get_debug_info_returns_empty_array_before_boot(): void
    {
        $kernel = new Kernel(Environment::Testing, debug: true);

        $this->assertSame([], $kernel->getDebugInfo());
    }

    public function test_get_debug_info_returns_service_resolution_profile_in_debug_mode(): void
    {
        $kernel = new Kernel(Environment::Testing, debug: true);
        $kernel->addDefinition('foo', fn() => 'bar', true);
        $kernel->boot();

        $info = $kernel->getDebugInfo();

        $this->assertArrayHasKey('serviceResolutionProfile', $info);
        $this->assertArrayHasKey('resolved', $info['serviceResolutionProfile']);
        $this->assertArrayHasKey('unresolved', $info['serviceResolutionProfile']);
    }

    public function test_get_debug_info_tracks_resolved_services(): void
    {
        $kernel = new Kernel(Environment::Testing, debug: true);
        $kernel->addDefinition('foo', fn() => 'bar', true);
        $kernel->boot();

        $kernel->getContainer()->get('foo');

        $info = $kernel->getDebugInfo();

        $this->assertArrayHasKey('foo', $info['serviceResolutionProfile']['resolved']);
    }

    public function test_kernel_implements_debuggable_interface(): void
    {
        $kernel = new Kernel(Environment::Testing);

        $this->assertInstanceOf(\Georgeff\Kernel\Debug\DebuggableInterface::class, $kernel);
    }

    public function test_container_is_debug_container_in_debug_mode(): void
    {
        $kernel = new Kernel(Environment::Testing, debug: true);
        $kernel->boot();

        $this->assertInstanceOf(\Georgeff\Kernel\Debug\DebugContainer::class, $kernel->getContainer());
    }

    public function test_container_is_not_debug_container_when_not_debug(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $kernel->boot();

        $this->assertNotInstanceOf(\Georgeff\Kernel\Debug\DebugContainer::class, $kernel->getContainer());
    }

    public function test_it_throws_when_adding_definition_with_reserved_environment_id(): void
    {
        $kernel = new Kernel(Environment::Testing);

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Cannot overwrite a reserved service definition');

        $kernel->addDefinition('kernel.environment', fn() => 'fake');
    }

    public function test_it_throws_when_adding_definition_with_reserved_debug_id(): void
    {
        $kernel = new Kernel(Environment::Testing);

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Cannot overwrite a reserved service definition');

        $kernel->addDefinition('kernel.debug', fn() => 'fake');
    }

    // -------------------------------------------------------------------------
    // addModule()
    // -------------------------------------------------------------------------

    public function test_add_module_returns_the_kernel(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $module = $this->createStub(ModuleInterface::class);

        $result = $kernel->addModule($module);

        $this->assertSame($kernel, $result);
    }

    public function test_add_module_is_fluent(): void
    {
        $registeredA = false;
        $registeredB = false;

        $moduleA = new class($registeredA) implements ModuleInterface {
            public function __construct(private bool &$registered) {}
            public function register(KernelInterface $kernel): void { $this->registered = true; }
        };

        $moduleB = new class($registeredB) implements ModuleInterface {
            public function __construct(private bool &$registered) {}
            public function register(KernelInterface $kernel): void { $this->registered = true; }
        };

        $kernel = new Kernel(Environment::Testing);
        $kernel->addModule($moduleA)->addModule($moduleB);
        $kernel->boot();

        $this->assertTrue($registeredA);
        $this->assertTrue($registeredB);
    }

    public function test_add_module_throws_after_boot(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $kernel->boot();

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Kernel has already been booted, cannot add new modules');

        $kernel->addModule($this->createStub(ModuleInterface::class));
    }

    public function test_add_module_throws_when_locked(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $stub   = $this->createStub(ModuleInterface::class);

        $module = new class($kernel, $stub) implements ModuleInterface {
            public function __construct(
                private KernelInterface $kernel,
                private ModuleInterface $stub,
            ) {}

            public function register(KernelInterface $kernel): void
            {
                $this->kernel->addModule($this->stub);
            }
        };

        $kernel->addModule($module);

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Cannot add modules, modules are locked');

        $kernel->boot();
    }

    public function test_add_module_throws_on_duplicate(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $module = $this->createStub(ModuleInterface::class);

        $kernel->addModule($module);

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage(sprintf('Module "%s" has already been added', $module::class));

        $kernel->addModule(clone $module);
    }

    // -------------------------------------------------------------------------
    // addRepository()
    // -------------------------------------------------------------------------

    public function test_add_repository_returns_the_kernel(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $repo   = $this->createStub(ModuleRepositoryInterface::class);

        $result = $kernel->addRepository($repo);

        $this->assertSame($kernel, $result);
    }

    public function test_add_repository_throws_after_boot(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $kernel->boot();

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Kernel has already been booted, cannot add new module repositories');

        $kernel->addRepository($this->createStub(ModuleRepositoryInterface::class));
    }

    public function test_add_repository_throws_when_locked(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $stub   = $this->createStub(ModuleRepositoryInterface::class);

        $module = new class($kernel, $stub) implements ModuleInterface {
            public function __construct(
                private KernelInterface $kernel,
                private ModuleRepositoryInterface $stub,
            ) {}

            public function register(KernelInterface $kernel): void
            {
                $this->kernel->addRepository($this->stub);
            }
        };

        $kernel->addModule($module);

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Cannot add module repository, modules are locked');

        $kernel->boot();
    }

    public function test_add_repository_throws_on_duplicate(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $repo   = $this->createStub(ModuleRepositoryInterface::class);

        $kernel->addRepository($repo);

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage(sprintf('Module repository "%s" has already been added', $repo::class));

        $kernel->addRepository(clone $repo);
    }

    // -------------------------------------------------------------------------
    // Module integration
    // -------------------------------------------------------------------------

    public function test_module_register_is_called_during_boot(): void
    {
        $registered = false;

        $module = new class($registered) implements ModuleInterface {
            public function __construct(private bool &$registered) {}
            public function register(KernelInterface $kernel): void { $this->registered = true; }
        };

        $kernel = new Kernel(Environment::Testing);
        $kernel->addModule($module);
        $kernel->boot();

        $this->assertTrue($registered);
    }

    public function test_module_register_can_contribute_definitions(): void
    {
        $service = new \stdClass();

        $module = new class($service) implements ModuleInterface {
            public function __construct(private \stdClass $service) {}
            public function register(KernelInterface $kernel): void
            {
                $kernel->addDefinition('module.service', fn() => $this->service, true);
            }
        };

        $kernel = new Kernel(Environment::Testing);
        $kernel->addModule($module);
        $kernel->boot();

        $this->assertSame($service, $kernel->getContainer()->get('module.service'));
    }

    public function test_bootable_module_boot_is_called_after_container_is_built(): void
    {
        $receivedContainer = null;

        $module = new class($receivedContainer) implements BootableModuleInterface {
            public function __construct(private mixed &$receivedContainer) {}
            public function register(KernelInterface $kernel): void {}
            public function boot(ContainerInterface $container): void
            {
                $this->receivedContainer = $container;
            }
        };

        $kernel = new Kernel(Environment::Testing);
        $kernel->addModule($module);
        $kernel->boot();

        $this->assertInstanceOf(ContainerInterface::class, $receivedContainer);
        $this->assertSame($kernel->getContainer(), $receivedContainer);
    }

    public function test_repository_modules_are_registered_during_boot(): void
    {
        $registered = false;

        $module = new class($registered) implements ModuleInterface {
            public function __construct(private bool &$registered) {}
            public function register(KernelInterface $kernel): void { $this->registered = true; }
        };

        $repo = new class($module) implements ModuleRepositoryInterface {
            public function __construct(private ModuleInterface $module) {}
            public function modules(Environment $env): array { return [$this->module]; }
        };

        $kernel = new Kernel(Environment::Testing);
        $kernel->addRepository($repo);
        $kernel->boot();

        $this->assertTrue($registered);
    }

    public function test_repository_receives_kernel_environment(): void
    {
        $receivedEnv = null;

        $repo = new class($receivedEnv) implements ModuleRepositoryInterface {
            public function __construct(private mixed &$receivedEnv) {}
            public function modules(Environment $env): array
            {
                $this->receivedEnv = $env;
                return [];
            }
        };

        $kernel = new Kernel(Environment::Production);
        $kernel->addRepository($repo);
        $kernel->boot();

        $this->assertSame(Environment::Production, $receivedEnv);
    }

    // -------------------------------------------------------------------------
    // kernel.config
    // -------------------------------------------------------------------------

    public function test_kernel_config_is_available_in_container_after_boot(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $kernel->boot();

        $this->assertTrue($kernel->getContainer()->has('kernel.config'));
    }

    public function test_kernel_config_is_empty_without_configurable_modules(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $kernel->addModule($this->createStub(ModuleInterface::class));
        $kernel->boot();

        $this->assertSame([], $kernel->getContainer()->get('kernel.config'));
    }

    public function test_kernel_config_contains_merged_module_config(): void
    {
        $module = new class implements ConfigurableModuleInterface {
            public function register(KernelInterface $kernel): void {}
            public function config(Environment $env): array
            {
                return ['db.host' => 'localhost', 'cache.driver' => 'redis'];
            }
        };

        $kernel = new Kernel(Environment::Testing);
        $kernel->addModule($module);
        $kernel->boot();

        $this->assertSame(
            ['db.host' => 'localhost', 'cache.driver' => 'redis'],
            $kernel->getContainer()->get('kernel.config'),
        );
    }

    public function test_kernel_config_receives_kernel_environment(): void
    {
        $receivedEnv = null;

        $module = new class($receivedEnv) implements ConfigurableModuleInterface {
            public function __construct(private mixed &$receivedEnv) {}
            public function register(KernelInterface $kernel): void {}
            public function config(Environment $env): array
            {
                $this->receivedEnv = $env;
                return [];
            }
        };

        $kernel = new Kernel(Environment::Production);
        $kernel->addModule($module);
        $kernel->boot();

        $this->assertSame(Environment::Production, $receivedEnv);
    }

    public function test_kernel_config_is_reserved(): void
    {
        $kernel = new Kernel(Environment::Testing);

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Cannot overwrite a reserved service definition');

        $kernel->addDefinition('kernel.config', fn() => []);
    }

    // -------------------------------------------------------------------------
    // Debug info — module phases
    // -------------------------------------------------------------------------

    public function test_get_debug_info_includes_module_phases(): void
    {
        $kernel = new Kernel(Environment::Testing, debug: true);
        $kernel->boot();

        $phases = $kernel->getDebugInfo()['bootProfile']['phases'];

        $this->assertArrayHasKey('moduleLoad', $phases);
        $this->assertArrayHasKey('moduleRegistration', $phases);
        $this->assertArrayHasKey('moduleBoot', $phases);
    }

    public function test_get_debug_info_includes_module_loader_info(): void
    {
        $kernel = new Kernel(Environment::Testing, debug: true);
        $kernel->addModule($this->createStub(ModuleInterface::class));
        $kernel->boot();

        $info = $kernel->getDebugInfo();

        $this->assertArrayHasKey('modules', $info);
        $this->assertArrayHasKey('loaded', $info['modules']);
        $this->assertArrayHasKey('registered', $info['modules']);
        $this->assertArrayHasKey('booted', $info['modules']);
        $this->assertArrayHasKey('modules', $info['modules']);
    }

    // -------------------------------------------------------------------------
    // shutdown / isShutdown
    // -------------------------------------------------------------------------

    public function test_it_is_not_shutdown_before_shutdown(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $kernel->boot();

        $this->assertFalse($kernel->isShutdown());
    }

    public function test_it_is_not_shutdown_before_boot(): void
    {
        $kernel = new Kernel(Environment::Testing);

        $this->assertFalse($kernel->isShutdown());
    }

    public function test_it_shuts_down(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $kernel->boot();
        $kernel->shutdown();

        $this->assertTrue($kernel->isShutdown());
    }

    public function test_shutdown_is_idempotent(): void
    {
        $calls = 0;

        $kernel = new Kernel(Environment::Testing);
        $kernel->onShutdown(function () use (&$calls) {
            $calls++;
        });
        $kernel->boot();
        $kernel->shutdown();
        $kernel->shutdown();

        $this->assertSame(1, $calls);
    }

    public function test_shutdown_is_a_no_op_when_not_booted(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $kernel->shutdown();

        $this->assertFalse($kernel->isShutdown());
    }

    // -------------------------------------------------------------------------
    // onShutdown
    // -------------------------------------------------------------------------

    public function test_on_shutdown_callback_is_called_during_shutdown(): void
    {
        $called = false;

        $kernel = new Kernel(Environment::Testing);
        $kernel->onShutdown(function (KernelInterface $k) use (&$called) {
            $called = true;
        });
        $kernel->boot();
        $kernel->shutdown();

        $this->assertTrue($called);
    }

    public function test_on_shutdown_callback_receives_kernel(): void
    {
        $received = null;

        $kernel = new Kernel(Environment::Testing);
        $kernel->onShutdown(function (KernelInterface $k) use (&$received) {
            $received = $k;
        });
        $kernel->boot();
        $kernel->shutdown();

        $this->assertSame($kernel, $received);
    }

    public function test_on_shutdown_callback_fires_before_shutdown_flag_is_set(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $kernel->onShutdown(function (KernelInterface $k) {
            $this->assertFalse($k->isShutdown());
        });
        $kernel->boot();
        $kernel->shutdown();
    }

    public function test_multiple_on_shutdown_callbacks_are_called_in_order(): void
    {
        $order = [];

        $kernel = new Kernel(Environment::Testing);
        $kernel->onShutdown(function () use (&$order) {
            $order[] = 'first';
        });
        $kernel->onShutdown(function () use (&$order) {
            $order[] = 'second';
        });
        $kernel->boot();
        $kernel->shutdown();

        $this->assertSame(['first', 'second'], $order);
    }

    public function test_on_shutdown_returns_the_kernel(): void
    {
        $kernel = new Kernel(Environment::Testing);

        $result = $kernel->onShutdown(function () {});

        $this->assertSame($kernel, $result);
    }

    public function test_on_shutdown_can_be_registered_after_boot(): void
    {
        $called = false;

        $kernel = new Kernel(Environment::Testing);
        $kernel->boot();
        $kernel->onShutdown(function () use (&$called) {
            $called = true;
        });
        $kernel->shutdown();

        $this->assertTrue($called);
    }

    public function test_it_throws_when_registering_on_shutdown_after_shutdown(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $kernel->boot();
        $kernel->shutdown();

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Kernel has already been shutdown, cannot add new pre-shutdown callbacks');

        $kernel->onShutdown(function () {});
    }

    // -------------------------------------------------------------------------
    // afterShutdown
    // -------------------------------------------------------------------------

    public function test_after_shutdown_callback_is_called_after_shutdown(): void
    {
        $called = false;

        $kernel = new Kernel(Environment::Testing);
        $kernel->afterShutdown(function (KernelInterface $k) use (&$called) {
            $called = true;
        });
        $kernel->boot();
        $kernel->shutdown();

        $this->assertTrue($called);
    }

    public function test_after_shutdown_callback_receives_kernel(): void
    {
        $received = null;

        $kernel = new Kernel(Environment::Testing);
        $kernel->afterShutdown(function (KernelInterface $k) use (&$received) {
            $received = $k;
        });
        $kernel->boot();
        $kernel->shutdown();

        $this->assertSame($kernel, $received);
    }

    public function test_after_shutdown_callback_fires_after_shutdown_flag_is_set(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $kernel->afterShutdown(function (KernelInterface $k) {
            $this->assertTrue($k->isShutdown());
        });
        $kernel->boot();
        $kernel->shutdown();
    }

    public function test_multiple_after_shutdown_callbacks_are_called_in_order(): void
    {
        $order = [];

        $kernel = new Kernel(Environment::Testing);
        $kernel->afterShutdown(function () use (&$order) {
            $order[] = 'first';
        });
        $kernel->afterShutdown(function () use (&$order) {
            $order[] = 'second';
        });
        $kernel->boot();
        $kernel->shutdown();

        $this->assertSame(['first', 'second'], $order);
    }

    public function test_after_shutdown_returns_the_kernel(): void
    {
        $kernel = new Kernel(Environment::Testing);

        $result = $kernel->afterShutdown(function () {});

        $this->assertSame($kernel, $result);
    }

    public function test_after_shutdown_can_be_registered_after_boot(): void
    {
        $called = false;

        $kernel = new Kernel(Environment::Testing);
        $kernel->boot();
        $kernel->afterShutdown(function () use (&$called) {
            $called = true;
        });
        $kernel->shutdown();

        $this->assertTrue($called);
    }

    public function test_it_throws_when_registering_after_shutdown_after_shutdown(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $kernel->boot();
        $kernel->shutdown();

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Kernel has already been shutdown, cannot add new post-shutdown callbacks');

        $kernel->afterShutdown(function () {});
    }

    public function test_on_shutdown_fires_before_after_shutdown(): void
    {
        $order = [];

        $kernel = new Kernel(Environment::Testing);
        $kernel->onShutdown(function () use (&$order) {
            $order[] = 'onShutdown';
        });
        $kernel->afterShutdown(function () use (&$order) {
            $order[] = 'afterShutdown';
        });
        $kernel->boot();
        $kernel->shutdown();

        $this->assertSame(['onShutdown', 'afterShutdown'], $order);
    }

    // -------------------------------------------------------------------------
    // tag()
    // -------------------------------------------------------------------------

    public function test_tag_returns_the_kernel(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $kernel->addDefinition('my.service', fn() => new \stdClass());

        $result = $kernel->tag('my.service', ['my.tag']);

        $this->assertSame($kernel, $result);
    }

    public function test_tag_throws_after_boot(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $kernel->boot();

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Kernel has already been booted, cannot add new container definition tags');

        $kernel->tag('kernel', ['some.tag']);
    }

    public function test_tag_does_not_create_duplicate_entries(): void
    {
        $service = new \stdClass();

        $kernel = new Kernel(Environment::Testing);
        $kernel->addDefinition('my.service', fn() => $service, true);
        $kernel->tag('my.service', ['my.tag']);
        $kernel->tag('my.service', ['my.tag']);
        $kernel->boot();

        $this->assertCount(1, $kernel->getContainer()->get(TagRegistryInterface::class)->getTagged('my.tag'));
    }

    // -------------------------------------------------------------------------
    // TagRegistry
    // -------------------------------------------------------------------------

    public function test_it_registers_tag_registry_in_container(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $kernel->boot();

        $this->assertTrue($kernel->getContainer()->has(TagRegistryInterface::class));
        $this->assertInstanceOf(TagRegistryInterface::class, $kernel->getContainer()->get(TagRegistryInterface::class));
    }

    public function test_tag_registry_is_accessible_by_alias(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $kernel->boot();

        $container = $kernel->getContainer();

        $this->assertTrue($container->has('kernel.tag.registry'));
        $this->assertSame($container->get(TagRegistryInterface::class), $container->get('kernel.tag.registry'));
    }

    public function test_tag_registry_interface_is_reserved(): void
    {
        $kernel = new Kernel(Environment::Testing);

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Cannot overwrite a reserved service definition');

        $kernel->addDefinition(TagRegistryInterface::class, fn() => null);
    }

    public function test_kernel_tag_registry_alias_is_reserved(): void
    {
        $kernel = new Kernel(Environment::Testing);

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Cannot overwrite a reserved service definition');

        $kernel->addDefinition('foo', fn() => null, false, ['kernel.tag.registry']);
    }

    public function test_tag_registry_resolves_services_tagged_via_add_definition(): void
    {
        $service = new \stdClass();

        $kernel = new Kernel(Environment::Testing);
        $kernel->addDefinition('my.service', fn() => $service, true, [], ['my.tag']);
        $kernel->boot();

        $tagged = $kernel->getContainer()->get(TagRegistryInterface::class)->getTagged('my.tag');

        $this->assertSame([$service], $tagged);
    }

    public function test_tag_registry_resolves_services_tagged_via_tag_method(): void
    {
        $service = new \stdClass();

        $kernel = new Kernel(Environment::Testing);
        $kernel->addDefinition('my.service', fn() => $service, true);
        $kernel->tag('my.service', ['my.tag']);
        $kernel->boot();

        $tagged = $kernel->getContainer()->get(TagRegistryInterface::class)->getTagged('my.tag');

        $this->assertSame([$service], $tagged);
    }

    public function test_tag_registry_returns_empty_array_for_unknown_tag(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $kernel->boot();

        $tagged = $kernel->getContainer()->get(TagRegistryInterface::class)->getTagged('unknown.tag');

        $this->assertSame([], $tagged);
    }

    public function test_module_can_tag_services_from_another_module(): void
    {
        $service = new \stdClass();

        $definingModule = new class($service) implements ModuleInterface {
            public function __construct(private \stdClass $service) {}
            public function register(KernelInterface $kernel): void
            {
                $kernel->addDefinition('other.service', fn() => $this->service, true);
            }
        };

        $taggingModule = new class implements ModuleInterface {
            public function register(KernelInterface $kernel): void
            {
                $kernel->tag('other.service', ['shared.tag']);
            }
        };

        $kernel = new Kernel(Environment::Testing);
        $kernel->addModule($definingModule)->addModule($taggingModule);
        $kernel->boot();

        $tagged = $kernel->getContainer()->get(TagRegistryInterface::class)->getTagged('shared.tag');

        $this->assertSame([$service], $tagged);
    }

    private function createMockRegistrar(): ServiceRegistrar&\PHPUnit\Framework\MockObject\MockObject
    {
        return $this->createMock(ServiceRegistrar::class);
    }
}
