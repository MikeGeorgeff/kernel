<?php

namespace Georgeff\Kernel\Test;

use Georgeff\Kernel\Config\ConfigInterface;
use Georgeff\Kernel\Contract\AggregateModuleInterface;
use Georgeff\Kernel\Contract\BootableModuleInterface;
use Georgeff\Kernel\Contract\ConfigurableModuleInterface;
use Georgeff\Kernel\Contract\ContainerBuilderInterface;
use Georgeff\Kernel\Contract\EnvironmentInterface;
use Georgeff\Kernel\Contract\ModuleInterface;
use Georgeff\Kernel\DI\DefinitionInterface;
use Georgeff\Kernel\DI\TagRegistryInterface;
use Georgeff\Kernel\Environment\Development;
use Georgeff\Kernel\Environment\Local;
use Georgeff\Kernel\Environment\Production;
use Georgeff\Kernel\Environment\Staging;
use Georgeff\Kernel\Environment\Testing;
use Georgeff\Kernel\Exception\DefinitionException;
use Georgeff\Kernel\Exception\HookException;
use Georgeff\Kernel\Exception\KernelException;
use Georgeff\Kernel\Exception\ModuleException;
use Georgeff\Kernel\Kernel;
use Georgeff\Kernel\KernelInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class KernelTest extends TestCase
{
    public function test_it_implements_kernel_interface(): void
    {
        $kernel = new Kernel(new Testing());

        $this->assertInstanceOf(KernelInterface::class, $kernel);
    }

    public function test_it_returns_the_environment(): void
    {
        $environment = new Production();
        $kernel = new Kernel($environment);

        $this->assertSame($environment, $kernel->getEnvironment());
    }

    public function test_it_returns_each_environment_instance(): void
    {
        $environments = [
            new Local(),
            new Development(),
            new Staging(),
            new Testing(),
            new Production(),
        ];

        foreach ($environments as $env) {
            $kernel = new Kernel($env);

            $this->assertSame($env, $kernel->getEnvironment());
        }
    }

    public function test_debug_defaults_to_false(): void
    {
        $kernel = new Kernel(new Testing());

        $this->assertFalse($kernel->isDebug());
    }

    public function test_debug_can_be_enabled(): void
    {
        $kernel = new Kernel(new Testing(), debug: true);

        $this->assertTrue($kernel->isDebug());
    }

    public function test_it_is_not_booted_before_boot(): void
    {
        $kernel = new Kernel(new Testing());

        $this->assertFalse($kernel->isBooted());
    }

    public function test_it_boots(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->boot();

        $this->assertTrue($kernel->isBooted());
    }

    public function test_boot_is_idempotent(): void
    {
        $builder = $this->createContainerBuilderMock();

        $builder->expects($this->once())->method('getContainer');

        $kernel = new Kernel(new Testing(), $builder);
        $kernel->boot();
        $kernel->boot();

        $this->assertTrue($kernel->isBooted());
    }

    public function test_boot_throws_when_called_reentrantly_during_boot(): void
    {
        $kernel = new Kernel(new Testing());

        $module = new class implements ModuleInterface {
            public function register(KernelInterface $kernel): void
            {
                $kernel->boot();
            }
        };

        $kernel->addModule($module);

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Kernel is booting, cannot call boot again');

        $kernel->boot();
    }

    public function test_it_returns_the_container_after_boot(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->boot();

        $this->assertInstanceOf(ContainerInterface::class, $kernel->getContainer());
    }

    public function test_it_throws_when_accessing_container_before_boot(): void
    {
        $kernel = new Kernel(new Testing());

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Container is inaccessible, kernel has not been booted');

        $kernel->getContainer();
    }

    public function test_it_throws_when_accessing_container_after_shutdown(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->boot();
        $kernel->shutdown();

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Container is inaccessible, kernel is shutdown');

        $kernel->getContainer();
    }

    public function test_it_registers_user_definitions(): void
    {
        $service = new \stdClass();

        $kernel = new Kernel(new Testing());
        $kernel->define('my.service', fn() => $service)->share();
        $kernel->boot();

        $this->assertSame($service, $kernel->getContainer()->get('my.service'));
    }

    public function test_it_registers_user_definitions_with_aliases(): void
    {
        $service = new \stdClass();

        $kernel = new Kernel(new Testing());
        $kernel->define('my.service', fn() => $service)->share()->alias('MyServiceAlias');
        $kernel->boot();

        $container = $kernel->getContainer();

        $this->assertSame($service, $container->get('my.service'));
        $this->assertSame($service, $container->get('MyServiceAlias'));
    }

    public function test_define_returns_a_definition(): void
    {
        $kernel = new Kernel(new Testing());

        $definition = $kernel->define('foo', fn() => 'bar');

        $this->assertInstanceOf(DefinitionInterface::class, $definition);
    }

    public function test_define_throws_when_redefining_an_existing_id(): void
    {
        $kernel = new Kernel(new Testing());

        $kernel->define('foo', fn() => 'first')->share();

        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('Cannot redefine an existing definition ID: [foo]');

        $kernel->define('foo', fn() => 'second');
    }

    public function test_it_throws_when_defining_after_boot(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->boot();

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Kernel has already been booted, cannot add new container definitions');

        $kernel->define('foo', fn() => 'bar');
    }

    // -------------------------------------------------------------------------
    // defineFallback()
    // -------------------------------------------------------------------------

    public function test_define_fallback_returns_a_definition(): void
    {
        $kernel = new Kernel(new Testing());

        $definition = $kernel->defineFallback('foo', fn() => 'bar');

        $this->assertInstanceOf(DefinitionInterface::class, $definition);
    }

    public function test_define_fallback_throws_after_boot(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->boot();

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Kernel has already been booted, cannot add new definition fallbacks');

        $kernel->defineFallback('foo', fn() => 'bar');
    }

    public function test_define_fallback_is_used_when_nothing_else_defines_the_id(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->defineFallback('foo', fn() => 'fallback')->share();
        $kernel->boot();

        $this->assertSame('fallback', $kernel->getContainer()->get('foo'));
    }

    public function test_define_fallback_is_ignored_when_a_real_definition_exists(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->define('foo', fn() => 'real')->share();
        $kernel->defineFallback('foo', fn() => 'fallback');
        $kernel->boot();

        $this->assertSame('real', $kernel->getContainer()->get('foo'));
    }

    public function test_define_fallback_registered_after_the_real_definition_is_still_ignored(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->defineFallback('foo', fn() => 'fallback');
        $kernel->define('foo', fn() => 'real')->share();
        $kernel->boot();

        $this->assertSame('real', $kernel->getContainer()->get('foo'));
    }

    public function test_two_modules_can_define_the_same_fallback_without_either_winning_deterministically_causing_an_error(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->defineFallback('foo', fn() => 'first')->share();
        $kernel->defineFallback('foo', fn() => 'second')->share();
        $kernel->boot();

        // Neither package cares which fallback wins, only that something does.
        $this->assertContains($kernel->getContainer()->get('foo'), ['first', 'second']);
    }

    public function test_override_can_target_an_id_that_only_exists_via_a_fallback(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->defineFallback('foo', fn() => 'fallback');
        $kernel->override('foo', fn() => 'overridden');
        $kernel->boot();

        $this->assertSame('overridden', $kernel->getContainer()->get('foo'));
    }

    public function test_on_booting_callback_is_called_during_boot(): void
    {
        $called = false;

        $kernel = new Kernel(new Testing());
        $kernel->onBooting(function (KernelInterface $k) use (&$called) {
            $called = true;
        });

        $kernel->boot();

        $this->assertTrue($called);
    }

    public function test_on_booting_callback_receives_kernel(): void
    {
        $received = null;

        $kernel = new Kernel(new Testing());
        $kernel->onBooting(function (KernelInterface $k) use (&$received) {
            $received = $k;
        });

        $kernel->boot();

        $this->assertSame($kernel, $received);
    }

    public function test_multiple_on_booting_callbacks_are_called_in_order(): void
    {
        $order = [];

        $kernel = new Kernel(new Testing());
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
        $kernel = new Kernel(new Testing());

        $result = $kernel->onBooting(function () {});

        $this->assertSame($kernel, $result);
    }

    public function test_it_throws_when_registering_on_booting_after_boot(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->boot();

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Kernel has already been booted, cannot add new pre-boot callbacks');

        $kernel->onBooting(function () {});
    }

    public function test_on_booted_callback_is_called_after_boot(): void
    {
        $called = false;

        $kernel = new Kernel(new Testing());
        $kernel->onBooted(function (KernelInterface $k) use (&$called) {
            $called = true;
        });

        $kernel->boot();

        $this->assertTrue($called);
    }

    public function test_on_booted_callback_receives_kernel(): void
    {
        $received = null;

        $kernel = new Kernel(new Testing());
        $kernel->onBooted(function (KernelInterface $k) use (&$received) {
            $received = $k;
        });

        $kernel->boot();

        $this->assertSame($kernel, $received);
    }

    public function test_on_booted_callback_fires_when_kernel_is_already_booted(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->onBooted(function (KernelInterface $k) {
            $this->assertTrue($k->isBooted());
        });

        $kernel->boot();
    }

    public function test_multiple_on_booted_callbacks_are_called_in_order(): void
    {
        $order = [];

        $kernel = new Kernel(new Testing());
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
        $kernel = new Kernel(new Testing());

        $result = $kernel->onBooted(function () {});

        $this->assertSame($kernel, $result);
    }

    public function test_it_throws_when_registering_on_booted_after_boot(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->boot();

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Kernel has already been booted, cannot add new post-boot callbacks');

        $kernel->onBooted(function () {});
    }

    public function test_on_booting_can_define_services(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->onBooting(function (KernelInterface $k): void {
            $k->define('dynamic', fn() => 'added_in_booting')->share();
        });

        $kernel->boot();

        $this->assertSame('added_in_booting', $kernel->getContainer()->get('dynamic'));
    }

    public function test_boot_throws_hook_exception_when_an_on_booting_callback_throws(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->onBooting(function () {
            throw new \RuntimeException('boom');
        });

        $this->expectException(HookException::class);
        $this->expectExceptionMessage('Hook callback for [onBooting] failed: boom');

        $kernel->boot();
    }

    public function test_boot_stops_at_the_first_failing_on_booted_callback(): void
    {
        $kernel    = new Kernel(new Testing());
        $secondRan = false;

        $kernel->onBooted(function () {
            throw new \RuntimeException('boom');
        });
        $kernel->onBooted(function () use (&$secondRan) {
            $secondRan = true;
        });

        try {
            $kernel->boot();
        } catch (HookException) {
        }

        $this->assertFalse($secondRan);
    }

    // -------------------------------------------------------------------------
    // onResolving() / onResolved()
    // -------------------------------------------------------------------------

    public function test_on_resolving_returns_the_kernel(): void
    {
        $kernel = new Kernel(new Testing());

        $result = $kernel->onResolving(function () {});

        $this->assertSame($kernel, $result);
    }

    public function test_on_resolved_returns_the_kernel(): void
    {
        $kernel = new Kernel(new Testing());

        $result = $kernel->onResolved(function () {});

        $this->assertSame($kernel, $result);
    }

    public function test_on_resolving_callback_fires_before_factory_runs(): void
    {
        $calls = [];

        $kernel = new Kernel(new Testing());
        $kernel->define('foo', fn() => 'bar')->share();
        $kernel->onResolving(function (string $id) use (&$calls) {
            $calls[] = $id;
        });
        $kernel->boot();

        $kernel->getContainer()->get('foo');

        $this->assertSame(['foo'], $calls);
    }

    public function test_on_resolving_fires_on_every_get_call_including_cache_hits(): void
    {
        $calls = 0;

        $kernel = new Kernel(new Testing());
        $kernel->define('foo', fn() => 'bar')->share();
        $kernel->onResolving(function () use (&$calls) {
            $calls++;
        });
        $kernel->boot();

        $kernel->getContainer()->get('foo');
        $kernel->getContainer()->get('foo');

        $this->assertSame(2, $calls);
    }

    public function test_on_resolved_callback_fires_after_factory_runs(): void
    {
        $calls = [];

        $kernel = new Kernel(new Testing());
        $kernel->define('foo', fn() => new \stdClass())->share();
        $kernel->onResolved(function (string $id, mixed $resolved) use (&$calls) {
            $calls[] = [$id, $resolved];
        });
        $kernel->boot();

        $instance = $kernel->getContainer()->get('foo');

        $this->assertCount(1, $calls);
        $this->assertSame('foo', $calls[0][0]);
        $this->assertSame($instance, $calls[0][1]);
    }

    public function test_on_resolved_does_not_fire_on_cache_hit(): void
    {
        $calls = 0;

        $kernel = new Kernel(new Testing());
        $kernel->define('foo', fn() => new \stdClass())->share();
        $kernel->onResolved(function () use (&$calls) {
            $calls++;
        });
        $kernel->boot();

        $kernel->getContainer()->get('foo');
        $kernel->getContainer()->get('foo');

        $this->assertSame(1, $calls);
    }

    public function test_multiple_on_resolved_callbacks_all_fire(): void
    {
        $fired = [];

        $kernel = new Kernel(new Testing());
        $kernel->define('foo', fn() => new \stdClass())->share();
        $kernel->onResolved(function () use (&$fired) { $fired[] = 'a'; });
        $kernel->onResolved(function () use (&$fired) { $fired[] = 'b'; });
        $kernel->boot();

        $kernel->getContainer()->get('foo');

        $this->assertSame(['a', 'b'], $fired);
    }

    public function test_module_can_register_resolution_hooks(): void
    {
        $calls = [];

        $module = new class implements ModuleInterface {
            public function register(KernelInterface $kernel): void
            {
                $kernel->define('foo', fn() => 'bar')->share();
            }
        };

        $kernel = new Kernel(new Testing());
        $kernel->addModule($module);
        $kernel->onResolved(function (string $id) use (&$calls) {
            $calls[] = $id;
        });
        $kernel->boot();

        $kernel->getContainer()->get('foo');

        $this->assertSame(['foo'], $calls);
    }

    public function test_it_throws_when_registering_on_resolving_after_boot(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->boot();

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Kernel has already been booted, cannot add new pre-resolution callbacks');

        $kernel->onResolving(function () {});
    }

    public function test_it_throws_when_registering_on_resolved_after_boot(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->boot();

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Kernel has already been booted, cannot add new post-resolution callbacks');

        $kernel->onResolved(function () {});
    }

    public function test_get_start_time_returns_negative_infinity_when_not_debug(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->boot();

        $this->assertSame(-INF, $kernel->getStartTime());
    }

    public function test_get_start_time_returns_float_when_debug(): void
    {
        $kernel = new Kernel(new Testing(), debug: true);
        $kernel->boot();

        $this->assertIsFloat($kernel->getStartTime());
        $this->assertGreaterThan(0, $kernel->getStartTime());
    }

    public function test_get_start_time_returns_negative_infinity_before_boot_without_debug(): void
    {
        $kernel = new Kernel(new Testing());

        $this->assertSame(-INF, $kernel->getStartTime());
    }

    public function test_it_uses_default_container_builder(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->boot();

        $this->assertInstanceOf(ContainerInterface::class, $kernel->getContainer());
    }

    public function test_it_uses_custom_container_builder(): void
    {
        $container = $this->createMock(ContainerInterface::class);

        $builder = $this->createContainerBuilderMock();
        $builder->method('getContainer')->willReturn($container);

        $kernel = new Kernel(new Testing(), $builder);
        $kernel->boot();

        $this->assertSame($container, $kernel->getContainer());
    }

    public function test_get_debug_info_returns_empty_array_when_not_debug(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->boot();

        $this->assertSame([], $kernel->getDebugInfo());
    }

    public function test_get_debug_info_returns_boot_profile_when_debug(): void
    {
        $kernel = new Kernel(new Testing(), debug: true);
        $kernel->boot();

        $info = $kernel->getDebugInfo();

        $this->assertArrayHasKey('boot.profile', $info);
        $this->assertArrayHasKey('start.time', $info['boot.profile']);
        $this->assertArrayHasKey('end.time', $info['boot.profile']);
        $this->assertArrayHasKey('duration', $info['boot.profile']);
        $this->assertArrayHasKey('phases', $info['boot.profile']);
        $this->assertArrayHasKey('preBoot', $info['boot.profile']['phases']);
        $this->assertArrayHasKey('serviceDecoration', $info['boot.profile']['phases']);
        $this->assertArrayHasKey('serviceRegistration', $info['boot.profile']['phases']);
        $this->assertArrayHasKey('containerInit', $info['boot.profile']['phases']);
    }

    public function test_get_debug_info_omits_shutdown_profile_before_shutdown(): void
    {
        $kernel = new Kernel(new Testing(), debug: true);
        $kernel->boot();

        $this->assertArrayNotHasKey('shutdown.profile', $kernel->getDebugInfo());
    }

    public function test_get_debug_info_returns_shutdown_profile_after_shutdown(): void
    {
        $kernel = new Kernel(new Testing(), debug: true);
        $kernel->boot();
        $kernel->shutdown();

        $info = $kernel->getDebugInfo();

        $this->assertArrayHasKey('shutdown.profile', $info);
        $this->assertArrayHasKey('start.time', $info['shutdown.profile']);
        $this->assertArrayHasKey('end.time', $info['shutdown.profile']);
        $this->assertArrayHasKey('duration', $info['shutdown.profile']);
        $this->assertArrayHasKey('phases', $info['shutdown.profile']);
        $this->assertArrayHasKey('shutdown', $info['shutdown.profile']['phases']);
        $this->assertArrayHasKey('afterShutdown', $info['shutdown.profile']['phases']);
    }

    public function test_profile_phase_is_stopped_even_when_callback_throws(): void
    {
        $module = new class implements ModuleInterface {
            public function register(KernelInterface $kernel): void
            {
                throw new \RuntimeException('boom');
            }
        };

        $kernel = new Kernel(new Testing(), debug: true);
        $kernel->addModule($module);

        try {
            $kernel->boot();
        } catch (\RuntimeException) {
        }

        $duration = $kernel->getDebugInfo()['boot.profile']['phases']['moduleRegistration']['duration'];

        $this->assertIsFloat($duration);
    }

    public function test_get_debug_info_returns_empty_array_before_boot(): void
    {
        $kernel = new Kernel(new Testing(), debug: true);

        $this->assertSame([], $kernel->getDebugInfo());
    }

    public function test_get_debug_info_returns_service_resolution_profile_in_debug_mode(): void
    {
        $kernel = new Kernel(new Testing(), debug: true);
        $kernel->define('foo', fn() => 'bar')->share();
        $kernel->boot();

        $info = $kernel->getDebugInfo();

        $this->assertArrayHasKey('services', $info);
        $this->assertArrayHasKey('resolved', $info['services']);
        $this->assertArrayHasKey('unresolved', $info['services']);
    }

    public function test_get_debug_info_includes_service_resetter_info_alongside_service_resolution(): void
    {
        $kernel = new Kernel(new Testing(), debug: true);
        $kernel->define('foo', fn() => 'bar')->share();
        $kernel->boot();

        $info = $kernel->getDebugInfo();

        $this->assertArrayHasKey('service.resetter', $info);
        $this->assertArrayHasKey('failures', $info['service.resetter']);
        $this->assertArrayHasKey('logs', $info['service.resetter']);
    }

    public function test_get_debug_info_omits_service_resetter_before_boot(): void
    {
        $kernel = new Kernel(new Testing(), debug: true);

        $this->assertArrayNotHasKey('service.resetter', $kernel->getDebugInfo());
    }

    public function test_get_debug_info_tracks_resolved_services(): void
    {
        $kernel = new Kernel(new Testing(), debug: true);
        $kernel->define('foo', fn() => 'bar')->share();
        $kernel->boot();

        $kernel->getContainer()->get('foo');

        $info = $kernel->getDebugInfo();

        $this->assertArrayHasKey('foo', $info['services']['resolved']);
    }

    public function test_get_debug_info_includes_debug_info_from_debuggable_resolved_service(): void
    {
        $service = new class implements \Georgeff\Kernel\Debug\DebuggableInterface {
            public function getDebugInfo(): array
            {
                return ['custom' => 'data'];
            }
        };

        $kernel = new Kernel(new Testing(), debug: true);
        $kernel->define('foo', fn() => $service)->share();
        $kernel->boot();

        $kernel->getContainer()->get('foo');

        $info = $kernel->getDebugInfo();

        $this->assertArrayHasKey('debugInfo', $info['services']['resolved']['foo']);
        $this->assertSame(['custom' => 'data'], $info['services']['resolved']['foo']['debugInfo']);
    }

    public function test_kernel_implements_debuggable_interface(): void
    {
        $kernel = new Kernel(new Testing());

        $this->assertInstanceOf(\Georgeff\Kernel\Debug\DebuggableInterface::class, $kernel);
    }

    // -------------------------------------------------------------------------
    // addModule()
    // -------------------------------------------------------------------------

    public function test_add_module_returns_the_kernel(): void
    {
        $kernel = new Kernel(new Testing());
        $module = $this->createStub(ModuleInterface::class);

        $result = $kernel->addModule($module);

        $this->assertSame($kernel, $result);
    }

    public function test_add_module_is_fluent(): void
    {
        $registeredA = false;
        $registeredB = false;

        $moduleA = new class ($registeredA) implements ModuleInterface {
            public function __construct(private bool &$registered) {}
            public function register(KernelInterface $kernel): void
            {
                $this->registered = true;
            }
        };

        $moduleB = new class ($registeredB) implements ModuleInterface {
            public function __construct(private bool &$registered) {}
            public function register(KernelInterface $kernel): void
            {
                $this->registered = true;
            }
        };

        $kernel = new Kernel(new Testing());
        $kernel->addModule($moduleA)->addModule($moduleB);
        $kernel->boot();

        $this->assertTrue($registeredA);
        $this->assertTrue($registeredB);
    }

    public function test_add_module_throws_after_boot(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->boot();

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Kernel has already been booted, cannot add new modules');

        $kernel->addModule($this->createStub(ModuleInterface::class));
    }

    public function test_add_module_throws_when_booting(): void
    {
        $kernel = new Kernel(new Testing());
        $stub   = $this->createStub(ModuleInterface::class);

        $module = new class ($kernel, $stub) implements ModuleInterface {
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
        $this->expectExceptionMessage('Cannot add modules after the kernel has started booting');

        $kernel->boot();
    }

    public function test_add_module_throws_on_duplicate(): void
    {
        $kernel = new Kernel(new Testing());
        $module = $this->createStub(ModuleInterface::class);

        $kernel->addModule($module);

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage(sprintf('Module [%s] has already been added', $module::class));

        $kernel->addModule(clone $module);
    }

    // -------------------------------------------------------------------------
    // getModules()
    // -------------------------------------------------------------------------

    public function test_get_modules_returns_an_empty_list_initially(): void
    {
        $kernel = new Kernel(new Testing());

        $this->assertSame([], $kernel->getModules());
    }

    public function test_get_modules_reflects_an_added_module_before_boot(): void
    {
        $kernel = new Kernel(new Testing());
        $module = $this->createStub(ModuleInterface::class);

        $kernel->addModule($module);

        $this->assertSame([$module::class], $kernel->getModules());
    }

    public function test_get_modules_reflects_added_modules_after_boot(): void
    {
        $kernel = new Kernel(new Testing());
        $module = $this->createStub(ModuleInterface::class);

        $kernel->addModule($module);
        $kernel->boot();

        $this->assertSame([$module::class], $kernel->getModules());
    }

    // -------------------------------------------------------------------------
    // Module integration
    // -------------------------------------------------------------------------

    public function test_module_register_is_called_during_boot(): void
    {
        $registered = false;

        $module = new class ($registered) implements ModuleInterface {
            public function __construct(private bool &$registered) {}
            public function register(KernelInterface $kernel): void
            {
                $this->registered = true;
            }
        };

        $kernel = new Kernel(new Testing());
        $kernel->addModule($module);
        $kernel->boot();

        $this->assertTrue($registered);
    }

    public function test_module_register_can_contribute_definitions(): void
    {
        $service = new \stdClass();

        $module = new class ($service) implements ModuleInterface {
            public function __construct(private \stdClass $service) {}
            public function register(KernelInterface $kernel): void
            {
                $kernel->define('module.service', fn() => $this->service)->share();
            }
        };

        $kernel = new Kernel(new Testing());
        $kernel->addModule($module);
        $kernel->boot();

        $this->assertSame($service, $kernel->getContainer()->get('module.service'));
    }

    public function test_bootable_module_boot_is_called_after_container_is_built(): void
    {
        $receivedContainer = null;

        $module = new class ($receivedContainer) implements BootableModuleInterface {
            public function __construct(private mixed &$receivedContainer) {}
            public function register(KernelInterface $kernel): void {}
            public function boot(ContainerInterface $container): void
            {
                $this->receivedContainer = $container;
            }
        };

        $kernel = new Kernel(new Testing());
        $kernel->addModule($module);
        $kernel->boot();

        $this->assertInstanceOf(ContainerInterface::class, $receivedContainer);
        $this->assertSame($kernel->getContainer(), $receivedContainer);
    }

    public function test_aggregate_module_modules_are_registered_during_boot(): void
    {
        $registered = false;

        $module = new class ($registered) implements ModuleInterface {
            public function __construct(private bool &$registered) {}
            public function register(KernelInterface $kernel): void
            {
                $this->registered = true;
            }
        };

        $aggregate = new class ($module) implements AggregateModuleInterface {
            public function __construct(private ModuleInterface $module) {}
            public function register(KernelInterface $kernel): void {}
            public function modules(EnvironmentInterface $env): array
            {
                return [$this->module];
            }
        };

        $kernel = new Kernel(new Testing());
        $kernel->addModule($aggregate);
        $kernel->boot();

        $this->assertTrue($registered);
    }

    public function test_aggregate_module_receives_kernel_environment(): void
    {
        $receivedEnv = null;
        $environment = new Production();

        $aggregate = new class ($receivedEnv) implements AggregateModuleInterface {
            public function __construct(private mixed &$receivedEnv) {}
            public function register(KernelInterface $kernel): void {}
            public function modules(EnvironmentInterface $env): array
            {
                $this->receivedEnv = $env;
                return [];
            }
        };

        $kernel = new Kernel($environment);
        $kernel->addModule($aggregate);
        $kernel->boot();

        $this->assertSame($environment, $receivedEnv);
    }

    // -------------------------------------------------------------------------
    // kernel.config
    // -------------------------------------------------------------------------

    public function test_config_is_available_in_container_after_boot(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->boot();

        $this->assertTrue($kernel->getContainer()->has(ConfigInterface::class));
        $this->assertInstanceOf(ConfigInterface::class, $kernel->getContainer()->get(ConfigInterface::class));
    }

    public function test_config_is_shared(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->boot();

        $container = $kernel->getContainer();

        $this->assertSame($container->get(ConfigInterface::class), $container->get(ConfigInterface::class));
    }

    public function test_config_has_no_module_keys_without_configurable_modules(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->addModule($this->createStub(ModuleInterface::class));
        $kernel->boot();

        $config = $kernel->getContainer()->get(ConfigInterface::class);

        $this->assertFalse($config->has('db.host'));
    }

    public function test_config_contains_merged_module_config(): void
    {
        $module = new class implements ConfigurableModuleInterface {
            public function register(KernelInterface $kernel): void {}
            public function config(EnvironmentInterface $env): array
            {
                return ['db.host' => 'localhost', 'cache.driver' => 'redis'];
            }
        };

        $kernel = new Kernel(new Testing());
        $kernel->addModule($module);
        $kernel->boot();

        $config = $kernel->getContainer()->get(ConfigInterface::class);

        $this->assertSame('localhost', $config->get('db.host'));
        $this->assertSame('redis', $config->get('cache.driver'));
    }

    public function test_kernel_config_receives_kernel_environment(): void
    {
        $receivedEnv = null;
        $environment = new Production();

        $module = new class ($receivedEnv) implements ConfigurableModuleInterface {
            public function __construct(private mixed &$receivedEnv) {}
            public function register(KernelInterface $kernel): void {}
            public function config(EnvironmentInterface $env): array
            {
                $this->receivedEnv = $env;
                return [];
            }
        };

        $kernel = new Kernel($environment);
        $kernel->addModule($module);
        $kernel->boot();

        $this->assertSame($environment, $receivedEnv);
    }

    // -------------------------------------------------------------------------
    // Debug info — module phases
    // -------------------------------------------------------------------------

    public function test_get_debug_info_includes_module_phases(): void
    {
        $kernel = new Kernel(new Testing(), debug: true);
        $kernel->boot();

        $phases = $kernel->getDebugInfo()['boot.profile']['phases'];

        $this->assertArrayHasKey('moduleLoad', $phases);
        $this->assertArrayHasKey('moduleRegistration', $phases);
        $this->assertArrayHasKey('moduleBoot', $phases);
    }

    public function test_get_debug_info_includes_module_loader_info(): void
    {
        $kernel = new Kernel(new Testing(), debug: true);
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
        $kernel = new Kernel(new Testing());
        $kernel->boot();

        $this->assertFalse($kernel->isShutdown());
    }

    public function test_it_is_not_shutdown_before_boot(): void
    {
        $kernel = new Kernel(new Testing());

        $this->assertFalse($kernel->isShutdown());
    }

    public function test_it_shuts_down(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->boot();
        $kernel->shutdown();

        $this->assertTrue($kernel->isShutdown());
    }

    public function test_shutdown_is_idempotent(): void
    {
        $calls = 0;

        $kernel = new Kernel(new Testing());
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
        $kernel = new Kernel(new Testing());
        $kernel->shutdown();

        $this->assertFalse($kernel->isShutdown());
    }

    // -------------------------------------------------------------------------
    // onShutdown
    // -------------------------------------------------------------------------

    public function test_on_shutdown_callback_is_called_during_shutdown(): void
    {
        $called = false;

        $kernel = new Kernel(new Testing());
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

        $kernel = new Kernel(new Testing());
        $kernel->onShutdown(function (KernelInterface $k) use (&$received) {
            $received = $k;
        });
        $kernel->boot();
        $kernel->shutdown();

        $this->assertSame($kernel, $received);
    }

    public function test_on_shutdown_callback_fires_before_shutdown_flag_is_set(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->onShutdown(function (KernelInterface $k) {
            $this->assertFalse($k->isShutdown());
        });
        $kernel->boot();
        $kernel->shutdown();
    }

    public function test_multiple_on_shutdown_callbacks_are_called_in_order(): void
    {
        $order = [];

        $kernel = new Kernel(new Testing());
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
        $kernel = new Kernel(new Testing());

        $result = $kernel->onShutdown(function () {});

        $this->assertSame($kernel, $result);
    }

    public function test_on_shutdown_can_be_registered_after_boot(): void
    {
        $called = false;

        $kernel = new Kernel(new Testing());
        $kernel->boot();
        $kernel->onShutdown(function () use (&$called) {
            $called = true;
        });
        $kernel->shutdown();

        $this->assertTrue($called);
    }

    public function test_it_throws_when_registering_on_shutdown_after_shutdown(): void
    {
        $kernel = new Kernel(new Testing());
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

        $kernel = new Kernel(new Testing());
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

        $kernel = new Kernel(new Testing());
        $kernel->afterShutdown(function (KernelInterface $k) use (&$received) {
            $received = $k;
        });
        $kernel->boot();
        $kernel->shutdown();

        $this->assertSame($kernel, $received);
    }

    public function test_after_shutdown_callback_fires_after_shutdown_flag_is_set(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->afterShutdown(function (KernelInterface $k) {
            $this->assertTrue($k->isShutdown());
        });
        $kernel->boot();
        $kernel->shutdown();
    }

    public function test_multiple_after_shutdown_callbacks_are_called_in_order(): void
    {
        $order = [];

        $kernel = new Kernel(new Testing());
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
        $kernel = new Kernel(new Testing());

        $result = $kernel->afterShutdown(function () {});

        $this->assertSame($kernel, $result);
    }

    public function test_after_shutdown_can_be_registered_after_boot(): void
    {
        $called = false;

        $kernel = new Kernel(new Testing());
        $kernel->boot();
        $kernel->afterShutdown(function () use (&$called) {
            $called = true;
        });
        $kernel->shutdown();

        $this->assertTrue($called);
    }

    public function test_it_throws_when_registering_after_shutdown_after_shutdown(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->boot();
        $kernel->shutdown();

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Kernel has already been shutdown, cannot add new post-shutdown callbacks');

        $kernel->afterShutdown(function () {});
    }

    public function test_on_shutdown_fires_before_after_shutdown(): void
    {
        $order = [];

        $kernel = new Kernel(new Testing());
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

    public function test_shutdown_runs_every_on_shutdown_callback_even_if_an_earlier_one_throws(): void
    {
        $kernel    = new Kernel(new Testing());
        $secondRan = false;

        $kernel->onShutdown(function () {
            throw new \RuntimeException('boom');
        });
        $kernel->onShutdown(function () use (&$secondRan) {
            $secondRan = true;
        });
        $kernel->boot();

        try {
            $kernel->shutdown();
        } catch (HookException) {
        }

        $this->assertTrue($secondRan);
    }

    public function test_shutdown_throws_hook_exception_when_an_on_shutdown_callback_throws(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->onShutdown(function () {
            throw new \RuntimeException('boom');
        });
        $kernel->boot();

        $this->expectException(HookException::class);

        $kernel->shutdown();
    }

    public function test_is_shutdown_remains_false_when_an_on_shutdown_callback_throws(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->onShutdown(function () {
            throw new \RuntimeException('boom');
        });
        $kernel->boot();

        try {
            $kernel->shutdown();
        } catch (HookException) {
        }

        // The kernel is not marked as shut down when a shutdown callback fails —
        // shutdown did not actually complete.
        $this->assertFalse($kernel->isShutdown());
    }

    public function test_after_shutdown_callbacks_do_not_run_when_an_on_shutdown_callback_throws(): void
    {
        $kernel = new Kernel(new Testing());
        $called = false;

        $kernel->onShutdown(function () {
            throw new \RuntimeException('boom');
        });
        $kernel->afterShutdown(function () use (&$called) {
            $called = true;
        });
        $kernel->boot();

        try {
            $kernel->shutdown();
        } catch (HookException) {
        }

        $this->assertFalse($called);
    }

    // -------------------------------------------------------------------------
    // decorate()
    // -------------------------------------------------------------------------

    public function test_decorate_returns_the_kernel(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->define('my.service', fn() => new \stdClass());

        $result = $kernel->decorate('my.service', fn($inner, $c) => $inner);

        $this->assertSame($kernel, $result);
    }

    public function test_decorate_is_fluent(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->define('foo', fn() => 'foo')->share();
        $kernel->define('bar', fn() => 'bar')->share();

        $kernel
            ->decorate('foo', fn($inner, $c) => "decorated_{$inner}")
            ->decorate('bar', fn($inner, $c) => "decorated_{$inner}");

        $kernel->boot();

        $this->assertSame('decorated_foo', $kernel->getContainer()->get('foo'));
        $this->assertSame('decorated_bar', $kernel->getContainer()->get('bar'));
    }

    public function test_decorate_throws_after_boot(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->boot();

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Kernel has already been booted, cannot add new definition decorators');

        $kernel->decorate('my.service', fn($inner, $c) => $inner);
    }

    public function test_decorate_throws_when_definition_does_not_exist(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->decorate('missing.service', fn($inner, $c) => $inner);

        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('Cannot decorate a non-existing definition ID: [missing.service]');

        $kernel->boot();
    }

    public function test_decorate_wraps_the_inner_service(): void
    {
        $inner = new \stdClass();

        $kernel = new Kernel(new Testing());
        $kernel->define('my.service', fn() => $inner)->share();
        $kernel->decorate('my.service', fn($i, $c) => ['decorated' => $i]);
        $kernel->boot();

        $result = $kernel->getContainer()->get('my.service');

        $this->assertSame(['decorated' => $inner], $result);
    }

    public function test_decorate_passes_container_to_decorator(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->define('my.service', fn() => new \stdClass())->share();

        $receivedContainer = null;
        $kernel->decorate('my.service', function ($inner, $c) use (&$receivedContainer) {
            $receivedContainer = $c;
            return $inner;
        });

        $kernel->boot();
        $kernel->getContainer()->get('my.service');

        $this->assertSame($kernel->getContainer(), $receivedContainer);
    }

    public function test_decorate_inherits_shared_from_original(): void
    {
        $calls = 0;

        $kernel = new Kernel(new Testing());
        $kernel->define('my.service', fn() => new \stdClass())->share();
        $kernel->decorate('my.service', function ($inner, $c) use (&$calls) {
            $calls++;
            return $inner;
        });

        $kernel->boot();
        $kernel->getContainer()->get('my.service');
        $kernel->getContainer()->get('my.service');

        $this->assertSame(1, $calls);
    }

    public function test_decorate_inherits_alias_from_original(): void
    {
        $inner = new \stdClass();

        $kernel = new Kernel(new Testing());
        $kernel->define('my.service', fn() => $inner)->share()->alias('my.alias');
        $kernel->decorate('my.service', fn($i, $c) => ['decorated' => $i]);
        $kernel->boot();

        $container = $kernel->getContainer();

        $this->assertSame($container->get('my.service'), $container->get('my.alias'));
    }

    public function test_decorate_inherits_tags_from_original(): void
    {
        $inner = new \stdClass();

        $kernel = new Kernel(new Testing());
        $kernel->define('my.service', fn() => $inner)->share()->tag('my.tag');
        $kernel->decorate('my.service', fn($i, $c) => ['decorated' => $i]);
        $kernel->boot();

        $tagged = $kernel->getContainer()->get(TagRegistryInterface::class)->getTagged('my.tag');

        $this->assertCount(1, $tagged);
        $this->assertSame(['decorated' => $inner], $tagged[0]);
    }

    public function test_module_can_decorate_service_from_another_module(): void
    {
        $inner = new \stdClass();

        $definingModule = new class ($inner) implements ModuleInterface {
            public function __construct(private \stdClass $inner) {}
            public function register(KernelInterface $kernel): void
            {
                $kernel->define('my.service', fn() => $this->inner)->share();
            }
        };

        $decoratingModule = new class implements ModuleInterface {
            public function register(KernelInterface $kernel): void
            {
                $kernel->decorate('my.service', fn($i, $c) => ['decorated' => $i]);
            }
        };

        $kernel = new Kernel(new Testing());
        $kernel->addModule($definingModule)->addModule($decoratingModule);
        $kernel->boot();

        $this->assertSame(['decorated' => $inner], $kernel->getContainer()->get('my.service'));
    }

    // -------------------------------------------------------------------------
    // override()
    // -------------------------------------------------------------------------

    public function test_override_returns_a_definition(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->define('my.service', fn() => 'original');

        $definition = $kernel->override('my.service', fn() => 'overridden');

        $this->assertInstanceOf(DefinitionInterface::class, $definition);
    }

    public function test_override_throws_after_boot(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->boot();

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Kernel has already been booted, cannot override service definitions');

        $kernel->override('my.service', fn() => 'overridden');
    }

    public function test_override_throws_when_definition_does_not_exist(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->override('missing.service', fn() => 'overridden');

        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('Cannot override a non-existing definition');

        $kernel->boot();
    }

    public function test_override_replaces_the_service(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->define('my.service', fn() => 'original');
        $kernel->override('my.service', fn() => 'overridden');
        $kernel->boot();

        $this->assertSame('overridden', $kernel->getContainer()->get('my.service'));
    }

    public function test_override_wins_over_a_module_defining_the_same_service(): void
    {
        // Reproduces the Junction test-harness scenario: registering an override
        // before boot() must still win even though the module registering the
        // real service hasn't run yet at the point override() is called - the
        // module's own define() call happens later, during moduleRegistration.
        $module = new class implements ModuleInterface {
            public function register(KernelInterface $kernel): void
            {
                $kernel->define('my.service', fn() => 'from module');
            }
        };

        $kernel = new Kernel(new Testing());
        $kernel->addModule($module);
        $kernel->override('my.service', fn() => 'overridden');
        $kernel->boot();

        $this->assertSame('overridden', $kernel->getContainer()->get('my.service'));
    }

    public function test_override_does_not_inherit_shared_from_original(): void
    {
        $calls = 0;

        $kernel = new Kernel(new Testing());
        $kernel->define('my.service', fn() => 'original')->share();
        $kernel->override('my.service', function () use (&$calls) {
            $calls++;
            return 'overridden';
        });
        $kernel->boot();

        $kernel->getContainer()->get('my.service');
        $kernel->getContainer()->get('my.service');

        $this->assertSame(2, $calls);
    }

    public function test_override_can_be_configured_as_shared_independently_of_original(): void
    {
        $calls = 0;

        $kernel = new Kernel(new Testing());
        $kernel->define('my.service', fn() => 'original');
        $kernel->override('my.service', function () use (&$calls) {
            $calls++;
            return 'overridden';
        })->share();
        $kernel->boot();

        $kernel->getContainer()->get('my.service');
        $kernel->getContainer()->get('my.service');

        $this->assertSame(1, $calls);
    }

    public function test_override_does_not_inherit_aliases_from_original(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->define('my.service', fn() => 'original')->share()->alias('my.alias');
        $kernel->override('my.service', fn() => 'overridden');
        $kernel->boot();

        $this->expectException(\Georgeff\Container\DefinitionNotFoundException::class);

        $kernel->getContainer()->get('my.alias');
    }

    public function test_override_does_not_inherit_tags_from_original(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->define('my.service', fn() => 'original')->share()->tag('my.tag');
        $kernel->override('my.service', fn() => 'overridden');
        $kernel->boot();

        $tagged = $kernel->getContainer()->get(TagRegistryInterface::class)->getTagged('my.tag');

        $this->assertCount(0, $tagged);
    }

    public function test_override_with_preserve_inherits_shared_aliases_and_tags(): void
    {
        // Mirrors overriding a real module-registered service (e.g. a queue) with a
        // fake that should slot into the same shared/alias/tag identity as the
        // original, without the caller having to already know what that identity is.
        $calls = 0;

        $kernel = new Kernel(new Testing());
        $kernel->define('my.service', fn() => 'original')->share()->alias('my.alias')->tag('my.tag');
        $kernel->override('my.service', function () use (&$calls) {
            $calls++;
            return 'overridden';
        }, preserve: true);
        $kernel->boot();

        $container = $kernel->getContainer();

        $this->assertSame('overridden', $container->get('my.service'));
        $this->assertSame('overridden', $container->get('my.alias'));

        $container->get('my.service');

        $this->assertSame(1, $calls);

        $tagged = $container->get(TagRegistryInterface::class)->getTagged('my.tag');

        $this->assertSame(['overridden'], $tagged);
    }

    public function test_module_can_override_service_from_another_module(): void
    {
        $definingModule = new class implements ModuleInterface {
            public function register(KernelInterface $kernel): void
            {
                $kernel->define('my.service', fn() => 'from module');
            }
        };

        $overridingModule = new class implements ModuleInterface {
            public function register(KernelInterface $kernel): void
            {
                $kernel->override('my.service', fn() => 'overridden');
            }
        };

        $kernel = new Kernel(new Testing());
        $kernel->addModule($definingModule)->addModule($overridingModule);
        $kernel->boot();

        $this->assertSame('overridden', $kernel->getContainer()->get('my.service'));
    }

    // -------------------------------------------------------------------------
    // TagRegistry
    // -------------------------------------------------------------------------

    public function test_it_registers_tag_registry_in_container(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->boot();

        $this->assertTrue($kernel->getContainer()->has(TagRegistryInterface::class));
        $this->assertInstanceOf(TagRegistryInterface::class, $kernel->getContainer()->get(TagRegistryInterface::class));
    }

    public function test_tag_registry_resolves_services_tagged_via_define(): void
    {
        $service = new \stdClass();

        $kernel = new Kernel(new Testing());
        $kernel->define('my.service', fn() => $service)->share()->tag('my.tag');
        $kernel->boot();

        $tagged = $kernel->getContainer()->get(TagRegistryInterface::class)->getTagged('my.tag');

        $this->assertSame([$service], $tagged);
    }

    public function test_tag_does_not_create_duplicate_entries(): void
    {
        $service = new \stdClass();

        $kernel = new Kernel(new Testing());
        $kernel->define('my.service', fn() => $service)->share()->tag('my.tag')->tag('my.tag');
        $kernel->boot();

        $this->assertCount(1, $kernel->getContainer()->get(TagRegistryInterface::class)->getTagged('my.tag'));
    }

    public function test_tag_registry_returns_empty_array_for_unknown_tag(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->boot();

        $tagged = $kernel->getContainer()->get(TagRegistryInterface::class)->getTagged('unknown.tag');

        $this->assertSame([], $tagged);
    }

    // -------------------------------------------------------------------------
    // resetServices()
    // -------------------------------------------------------------------------

    public function test_reset_services_returns_the_kernel(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->boot();

        $result = $kernel->resetServices();

        $this->assertSame($kernel, $result);
    }

    public function test_reset_services_throws_before_boot(): void
    {
        $kernel = new Kernel(new Testing());

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Kernel has not been booted, cannot reset shared services');

        $kernel->resetServices();
    }

    public function test_reset_services_throws_after_shutdown(): void
    {
        $kernel = new Kernel(new Testing());
        $kernel->boot();
        $kernel->shutdown();

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Kernel is shutdown, cannot reset shared services');

        $kernel->resetServices();
    }

    public function test_reset_services_resets_a_resolved_shared_resettable_service(): void
    {
        $service = new class implements \Georgeff\Kernel\Contract\ResettableInterface {
            public int $count = 0;
            public function reset(): void
            {
                $this->count = 0;
            }
        };

        $kernel = new Kernel(new Testing());
        $kernel->define('my.service', fn() => $service)->share();
        $kernel->boot();

        $resolved = $kernel->getContainer()->get('my.service');
        $resolved->count = 5;

        $kernel->resetServices();

        $this->assertSame(0, $resolved->count);
    }

    public function test_reset_services_does_not_reset_a_service_that_was_never_resolved(): void
    {
        $service = new class implements \Georgeff\Kernel\Contract\ResettableInterface {
            public int $count = 5;
            public function reset(): void
            {
                $this->count = 0;
            }
        };

        $kernel = new Kernel(new Testing());
        $kernel->define('my.service', fn() => $service)->share();
        $kernel->boot();

        // Never resolved via $kernel->getContainer()->get('my.service')
        $kernel->resetServices();

        $this->assertSame(5, $service->count);
    }

    public function test_reset_services_does_not_reset_a_non_shared_resettable_service(): void
    {
        $service = new class implements \Georgeff\Kernel\Contract\ResettableInterface {
            public int $count = 0;
            public function reset(): void
            {
                $this->count = 0;
            }
        };

        $kernel = new Kernel(new Testing());
        $kernel->define('my.service', fn() => $service);
        $kernel->boot();

        $resolved = $kernel->getContainer()->get('my.service');
        $resolved->count = 5;

        $kernel->resetServices();

        $this->assertSame(5, $resolved->count);
    }

    public function test_reset_services_does_not_reset_a_non_resettable_service(): void
    {
        $service = new \stdClass();
        $service->count = 5;

        $kernel = new Kernel(new Testing());
        $kernel->define('my.service', fn() => $service)->share();
        $kernel->boot();

        $kernel->getContainer()->get('my.service');
        $kernel->resetServices();

        $this->assertSame(5, $service->count);
    }

    public function test_shutdown_clears_service_resetter_failure_state(): void
    {
        $service = new class implements \Georgeff\Kernel\Contract\ResettableInterface {
            public function reset(): void
            {
                throw new \RuntimeException('boom');
            }
        };

        $kernel = new Kernel(new Testing(), debug: true);
        $kernel->define('my.service', fn() => $service)->share();
        $kernel->boot();
        $kernel->getContainer()->get('my.service');

        // One failure, well below the default threshold of 3 — logged, not thrown.
        $kernel->resetServices();

        $this->assertNotEmpty($kernel->getDebugInfo()['service.resetter']['failures']);
        $this->assertNotEmpty($kernel->getDebugInfo()['service.resetter']['logs']);

        $kernel->shutdown();

        $this->assertSame([], $kernel->getDebugInfo()['service.resetter']['failures']);
        $this->assertSame([], $kernel->getDebugInfo()['service.resetter']['logs']);
    }

    private function createContainerBuilderMock(): ContainerBuilderInterface&\PHPUnit\Framework\MockObject\MockObject
    {
        return $this->createMock(ContainerBuilderInterface::class);
    }
}
