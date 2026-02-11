<?php

namespace Georgeff\Kernel\Test\Debug;

use Georgeff\Kernel\Debug\DebuggableInterface;
use Georgeff\Kernel\Debug\DebugContainer;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class DebugContainerTest extends TestCase
{
    public function test_it_implements_container_interface(): void
    {
        $container = new DebugContainer($this->createMock(ContainerInterface::class), []);

        $this->assertInstanceOf(ContainerInterface::class, $container);
    }

    public function test_it_implements_debuggable_interface(): void
    {
        $container = new DebugContainer($this->createMock(ContainerInterface::class), []);

        $this->assertInstanceOf(DebuggableInterface::class, $container);
    }

    public function test_has_delegates_to_inner_container(): void
    {
        $inner = $this->createMock(ContainerInterface::class);
        $inner->method('has')->with('foo')->willReturn(true);

        $container = new DebugContainer($inner, []);

        $this->assertTrue($container->has('foo'));
    }

    public function test_has_returns_false_when_inner_returns_false(): void
    {
        $inner = $this->createMock(ContainerInterface::class);
        $inner->method('has')->with('foo')->willReturn(false);

        $container = new DebugContainer($inner, []);

        $this->assertFalse($container->has('foo'));
    }

    public function test_get_delegates_to_inner_container(): void
    {
        $service = new \stdClass();

        $inner = $this->createMock(ContainerInterface::class);
        $inner->method('get')->with('foo')->willReturn($service);

        $definitions = [
            'foo' => ['factory' => fn() => $service, 'shared' => true, 'aliases' => []],
        ];

        $container = new DebugContainer($inner, $definitions);

        $this->assertSame($service, $container->get('foo'));
    }

    public function test_get_tracks_resolution(): void
    {
        $inner = $this->createMock(ContainerInterface::class);
        $inner->method('get')->with('foo')->willReturn('bar');

        $definitions = [
            'foo' => ['factory' => fn() => 'bar', 'shared' => true, 'aliases' => []],
        ];

        $container = new DebugContainer($inner, $definitions);
        $container->get('foo');

        $info = $container->getDebugInfo();

        $this->assertArrayHasKey('foo', $info['serviceResolutionProfile']['resolved']);
        $this->assertSame(1, $info['serviceResolutionProfile']['resolved']['foo']['resolutionCount']);
    }

    public function test_get_tracks_multiple_resolutions(): void
    {
        $inner = $this->createMock(ContainerInterface::class);
        $inner->method('get')->with('foo')->willReturn('bar');

        $definitions = [
            'foo' => ['factory' => fn() => 'bar', 'shared' => false, 'aliases' => []],
        ];

        $container = new DebugContainer($inner, $definitions);
        $container->get('foo');
        $container->get('foo');

        $info = $container->getDebugInfo();

        $this->assertSame(2, $info['serviceResolutionProfile']['resolved']['foo']['resolutionCount']);
    }

    public function test_get_collects_debuggable_service_info(): void
    {
        $service = new class implements DebuggableInterface {
            public function getDebugInfo(): array
            {
                return ['custom' => 'info'];
            }
        };

        $inner = $this->createMock(ContainerInterface::class);
        $inner->method('get')->with('foo')->willReturn($service);

        $definitions = [
            'foo' => ['factory' => fn() => $service, 'shared' => true, 'aliases' => []],
        ];

        $container = new DebugContainer($inner, $definitions);
        $container->get('foo');

        $info = $container->getDebugInfo();

        $this->assertArrayHasKey('foo', $info['servicesDebugInfo']);
        $this->assertSame(['custom' => 'info'], $info['servicesDebugInfo']['foo']);
    }

    public function test_debuggable_service_info_is_lazy(): void
    {
        $callCount = 0;

        $service = new class($callCount) implements DebuggableInterface {
            private int $count;

            public function __construct(private int &$callCount)
            {
                $this->count = 0;
            }

            public function increment(): void
            {
                $this->count++;
            }

            public function getDebugInfo(): array
            {
                $this->callCount++;

                return ['count' => $this->count];
            }
        };

        $inner = $this->createMock(ContainerInterface::class);
        $inner->method('get')->with('foo')->willReturn($service);

        $definitions = [
            'foo' => ['factory' => fn() => $service, 'shared' => true, 'aliases' => []],
        ];

        $container = new DebugContainer($inner, $definitions);
        $container->get('foo');

        $service->increment();
        $service->increment();

        $info = $container->getDebugInfo();

        $this->assertSame(['count' => 2], $info['servicesDebugInfo']['foo']);
    }

    public function test_non_debuggable_service_not_collected(): void
    {
        $inner = $this->createMock(ContainerInterface::class);
        $inner->method('get')->with('foo')->willReturn('bar');

        $definitions = [
            'foo' => ['factory' => fn() => 'bar', 'shared' => true, 'aliases' => []],
        ];

        $container = new DebugContainer($inner, $definitions);
        $container->get('foo');

        $info = $container->getDebugInfo();

        $this->assertArrayNotHasKey('foo', $info['servicesDebugInfo']);
    }

    public function test_get_debug_info_returns_expected_structure(): void
    {
        $container = new DebugContainer($this->createMock(ContainerInterface::class), []);

        $info = $container->getDebugInfo();

        $this->assertArrayHasKey('serviceResolutionProfile', $info);
        $this->assertArrayHasKey('servicesDebugInfo', $info);
    }

    public function test_unresolved_services_tracked(): void
    {
        $inner = $this->createMock(ContainerInterface::class);
        $inner->method('get')->willReturn('value');

        $definitions = [
            'foo' => ['factory' => fn() => 'foo', 'shared' => true, 'aliases' => []],
            'bar' => ['factory' => fn() => 'bar', 'shared' => true, 'aliases' => []],
        ];

        $container = new DebugContainer($inner, $definitions);
        $container->get('foo');

        $info = $container->getDebugInfo();

        $this->assertSame(['bar'], $info['serviceResolutionProfile']['unresolved']);
    }
}
