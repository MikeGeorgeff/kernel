<?php

namespace Georgeff\Kernel\Test;

use Georgeff\Kernel\Environment;
use Georgeff\Kernel\Event\KernelBooted;
use Georgeff\Kernel\Kernel;
use Georgeff\Kernel\KernelException;
use Georgeff\Kernel\KernelInterface;
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

    private function createMockRegistrar(): ServiceRegistrar&\PHPUnit\Framework\MockObject\MockObject
    {
        return $this->createMock(ServiceRegistrar::class);
    }
}
