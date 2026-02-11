<?php

namespace Georgeff\Kernel\Test\Debug;

use Georgeff\Kernel\Debug\DebuggableInterface;
use Georgeff\Kernel\Debug\ResolvedService;
use PHPUnit\Framework\TestCase;

class ResolvedServiceTest extends TestCase
{
    public function test_it_implements_debuggable_interface(): void
    {
        $service = new ResolvedService('foo');

        $this->assertInstanceOf(DebuggableInterface::class, $service);
    }

    public function test_it_returns_the_id(): void
    {
        $service = new ResolvedService('foo');

        $this->assertSame('foo', $service->getId());
    }

    public function test_resolution_count_starts_at_zero(): void
    {
        $service = new ResolvedService('foo');

        $this->assertSame(0, $service->getResolutionCount());
    }

    public function test_resolution_time_starts_at_zero(): void
    {
        $service = new ResolvedService('foo');

        $this->assertSame(0.0, $service->getResolutionTime());
    }

    public function test_increment_resolution_count(): void
    {
        $service = new ResolvedService('foo');
        $service->incrementResolutionCount();

        $this->assertSame(1, $service->getResolutionCount());
    }

    public function test_increment_resolution_count_multiple_times(): void
    {
        $service = new ResolvedService('foo');
        $service->incrementResolutionCount();
        $service->incrementResolutionCount();
        $service->incrementResolutionCount();

        $this->assertSame(3, $service->getResolutionCount());
    }

    public function test_increment_resolution_count_returns_self(): void
    {
        $service = new ResolvedService('foo');

        $this->assertSame($service, $service->incrementResolutionCount());
    }

    public function test_add_resolution_time(): void
    {
        $service = new ResolvedService('foo');
        $service->addResolutionTime(1.5);

        $this->assertSame(1.5, $service->getResolutionTime());
    }

    public function test_add_resolution_time_accumulates(): void
    {
        $service = new ResolvedService('foo');
        $service->addResolutionTime(1.5);
        $service->addResolutionTime(2.5);

        $this->assertSame(4.0, $service->getResolutionTime());
    }

    public function test_add_resolution_time_returns_self(): void
    {
        $service = new ResolvedService('foo');

        $this->assertSame($service, $service->addResolutionTime(1.0));
    }

    public function test_fluent_chaining(): void
    {
        $service = new ResolvedService('foo');

        $result = $service->incrementResolutionCount()
                          ->addResolutionTime(1.0);

        $this->assertSame($service, $result);
        $this->assertSame(1, $service->getResolutionCount());
        $this->assertSame(1.0, $service->getResolutionTime());
    }

    public function test_get_debug_info(): void
    {
        $service = new ResolvedService('foo');
        $service->incrementResolutionCount()
                ->addResolutionTime(0.5);

        $this->assertSame([
            'foo' => [
                'resolutionCount'     => 1,
                'totalResolutionTime' => 0.5,
            ],
        ], $service->getDebugInfo());
    }
}
