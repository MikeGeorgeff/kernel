<?php

namespace Georgeff\Kernel\Test;

use Georgeff\Kernel\Environment;
use Georgeff\Kernel\Event\KernelBooted;
use Georgeff\Kernel\Event\KernelBooting;
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
        $this->assertFalse($kernel->isBooting());
    }

    public function test_it_boots(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $kernel->boot();

        $this->assertTrue($kernel->isBooted());
        $this->assertFalse($kernel->isBooting());
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

    public function test_it_registers_event_dispatcher_in_container(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $kernel = new Kernel(Environment::Testing, dispatcher: $dispatcher);
        $kernel->boot();

        $container = $kernel->getContainer();

        $this->assertTrue($container->has('event.dispatcher'));
        $this->assertSame($dispatcher, $container->get('event.dispatcher'));
    }

    public function test_it_aliases_event_dispatcher_as_interface(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $kernel = new Kernel(Environment::Testing, dispatcher: $dispatcher);
        $kernel->boot();

        $container = $kernel->getContainer();

        $this->assertTrue($container->has(EventDispatcherInterface::class));
        $this->assertSame($dispatcher, $container->get(EventDispatcherInterface::class));
    }

    public function test_it_does_not_register_event_dispatcher_when_not_provided(): void
    {
        $kernel = new Kernel(Environment::Testing);
        $kernel->boot();

        $this->assertFalse($kernel->getContainer()->has('event.dispatcher'));
    }

    public function test_it_throws_when_overwriting_reserved_event_dispatcher_id(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $kernel = new Kernel(Environment::Testing, dispatcher: $dispatcher);

        $this->expectException(KernelException::class);

        $kernel->addDefinition('event.dispatcher', fn() => 'fake');
    }

    public function test_it_throws_when_overwriting_reserved_event_dispatcher_interface_alias(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $kernel = new Kernel(Environment::Testing, dispatcher: $dispatcher);

        $this->expectException(KernelException::class);

        $kernel->addDefinition('foo', fn() => 'bar', false, [EventDispatcherInterface::class]);
    }

    public function test_event_dispatcher_ids_are_not_reserved_when_no_dispatcher(): void
    {
        $kernel = new Kernel(Environment::Testing);

        $kernel->addDefinition('event.dispatcher', fn() => 'custom', true);

        $this->assertSame($kernel, $kernel->addDefinition('foo', fn() => 'bar', false, [EventDispatcherInterface::class]));
    }

    public function test_it_dispatches_kernel_booting_event(): void
    {
        $events = [];

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(function (object $event) use (&$events) {
            $events[] = $event;
            return $event;
        });

        $kernel = new Kernel(Environment::Testing, dispatcher: $dispatcher);
        $kernel->boot();

        $this->assertInstanceOf(KernelBooting::class, $events[0]);
        $this->assertSame($kernel, $events[0]->kernel);
    }

    public function test_it_dispatches_kernel_booted_event(): void
    {
        $events = [];

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(function (object $event) use (&$events) {
            $events[] = $event;
            return $event;
        });

        $kernel = new Kernel(Environment::Testing, dispatcher: $dispatcher);
        $kernel->boot();

        $this->assertInstanceOf(KernelBooted::class, $events[1]);
        $this->assertSame($kernel, $events[1]->kernel);
    }

    public function test_kernel_is_booting_during_booting_event(): void
    {
        $wasBooting = null;

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(function (object $event) use (&$wasBooting) {
            if ($event instanceof KernelBooting) {
                $wasBooting = $event->kernel->isBooting();
            }
            return $event;
        });

        $kernel = new Kernel(Environment::Testing, dispatcher: $dispatcher);
        $kernel->boot();

        $this->assertTrue($wasBooting);
    }

    public function test_kernel_is_booted_during_booted_event(): void
    {
        $wasBooted = null;

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(function (object $event) use (&$wasBooted) {
            if ($event instanceof KernelBooted) {
                $wasBooted = $event->kernel->isBooted();
            }
            return $event;
        });

        $kernel = new Kernel(Environment::Testing, dispatcher: $dispatcher);
        $kernel->boot();

        $this->assertTrue($wasBooted);
    }

    public function test_it_does_not_dispatch_events_without_dispatcher(): void
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
