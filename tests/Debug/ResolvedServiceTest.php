<?php

namespace Georgeff\Kernel\Test\Debug;

use Georgeff\Kernel\Debug\DebuggableInterface;
use Georgeff\Kernel\Debug\ResolvedService;
use PHPUnit\Framework\TestCase;

class ResolvedServiceTest extends TestCase
{
    public function test_it_implements_debuggable_interface(): void
    {
        $service = new ResolvedService('foo', new \stdClass());

        $this->assertInstanceOf(DebuggableInterface::class, $service);
    }

    public function test_it_returns_the_id(): void
    {
        $service = new ResolvedService('foo', new \stdClass());

        $this->assertSame('foo', $service->getId());
    }

    public function test_resolution_count_starts_at_zero(): void
    {
        $service = new ResolvedService('foo', new \stdClass());

        $this->assertSame(0, $service->getResolutionCount());
    }

    public function test_increment_resolution_count(): void
    {
        $service = new ResolvedService('foo', new \stdClass());
        $service->incrementResolutionCount();

        $this->assertSame(1, $service->getResolutionCount());
    }

    public function test_increment_resolution_count_multiple_times(): void
    {
        $service = new ResolvedService('foo', new \stdClass());
        $service->incrementResolutionCount();
        $service->incrementResolutionCount();
        $service->incrementResolutionCount();

        $this->assertSame(3, $service->getResolutionCount());
    }

    public function test_increment_resolution_count_returns_self(): void
    {
        $service = new ResolvedService('foo', new \stdClass());

        $this->assertSame($service, $service->incrementResolutionCount());
    }

    public function test_get_debug_info_returns_resolution_count(): void
    {
        $service = new ResolvedService('foo', new \stdClass());
        $service->incrementResolutionCount();

        $info = $service->getDebugInfo();

        $this->assertSame(1, $info['resolutionCount']);
    }

    public function test_get_debug_info_includes_debug_info_for_debuggable_resolved(): void
    {
        $inner = new class implements DebuggableInterface {
            public function getDebugInfo(): array { return ['custom' => 'info']; }
        };

        $service = new ResolvedService('foo', $inner);

        $info = $service->getDebugInfo();

        $this->assertArrayHasKey('debugInfo', $info);
        $this->assertSame(['custom' => 'info'], $info['debugInfo']);
    }

    public function test_get_debug_info_does_not_include_debug_info_for_non_debuggable_resolved(): void
    {
        $service = new ResolvedService('foo', new \stdClass());

        $info = $service->getDebugInfo();

        $this->assertArrayNotHasKey('debugInfo', $info);
    }

    public function test_debuggable_resolved_info_is_evaluated_lazily(): void
    {
        $inner = new class implements DebuggableInterface {
            public int $count = 0;

            public function getDebugInfo(): array { return ['count' => $this->count]; }
        };

        $service = new ResolvedService('foo', $inner);

        $inner->count = 5;

        $info = $service->getDebugInfo();

        $this->assertSame(['count' => 5], $info['debugInfo']);
    }
}
