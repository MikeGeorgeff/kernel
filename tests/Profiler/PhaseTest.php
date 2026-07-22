<?php

namespace Georgeff\Kernel\Test\Profiler;

use Georgeff\Kernel\Contract\DebuggableInterface;
use Georgeff\Kernel\Profiler\Phase;
use PHPUnit\Framework\TestCase;

class PhaseTest extends TestCase
{
    public function test_it_implements_debuggable_interface(): void
    {
        $phase = new Phase('test');

        $this->assertInstanceOf(DebuggableInterface::class, $phase);
    }

    public function test_get_name_returns_the_constructor_value(): void
    {
        $phase = new Phase('testPhase');

        $this->assertSame('testPhase', $phase->getName());
    }

    public function test_start_returns_float(): void
    {
        $phase = new Phase('test');

        $this->assertIsFloat($phase->start());
    }

    public function test_stop_returns_float(): void
    {
        $phase = new Phase('test');
        $phase->start();

        $this->assertIsFloat($phase->stop());
    }

    public function test_get_start_time_returns_the_value_from_start(): void
    {
        $phase     = new Phase('test');
        $startedAt = $phase->start();

        $this->assertSame($startedAt, $phase->getStartTime());
    }

    public function test_get_start_time_returns_null_when_not_started(): void
    {
        $phase = new Phase('test');

        $this->assertNull($phase->getStartTime());
    }

    public function test_get_end_time_returns_the_value_from_stop(): void
    {
        $phase = new Phase('test');
        $phase->start();
        $stoppedAt = $phase->stop();

        $this->assertSame($stoppedAt, $phase->getEndTime());
    }

    public function test_get_end_time_returns_null_when_not_stopped(): void
    {
        $phase = new Phase('test');
        $phase->start();

        $this->assertNull($phase->getEndTime());
    }

    public function test_get_start_memory_returns_int_after_start(): void
    {
        $phase = new Phase('test');
        $phase->start();

        $this->assertIsInt($phase->getStartMemory());
    }

    public function test_get_start_memory_returns_null_when_not_started(): void
    {
        $phase = new Phase('test');

        $this->assertNull($phase->getStartMemory());
    }

    public function test_get_end_memory_returns_int_after_stop(): void
    {
        $phase = new Phase('test');
        $phase->start();
        $phase->stop();

        $this->assertIsInt($phase->getEndMemory());
    }

    public function test_get_end_memory_returns_null_when_not_stopped(): void
    {
        $phase = new Phase('test');
        $phase->start();

        $this->assertNull($phase->getEndMemory());
    }

    public function test_is_incomplete_is_true_before_start(): void
    {
        $phase = new Phase('test');

        $this->assertTrue($phase->isIncomplete());
    }

    public function test_is_incomplete_is_true_after_start_only(): void
    {
        $phase = new Phase('test');
        $phase->start();

        $this->assertTrue($phase->isIncomplete());
    }

    public function test_is_incomplete_is_false_after_start_and_stop(): void
    {
        $phase = new Phase('test');
        $phase->start();
        $phase->stop();

        $this->assertFalse($phase->isIncomplete());
    }

    public function test_get_duration_returns_positive_float_when_complete(): void
    {
        $phase = new Phase('test');
        $phase->start();
        $phase->stop();

        $this->assertIsFloat($phase->getDuration());
        $this->assertGreaterThanOrEqual(0.0, $phase->getDuration());
    }

    public function test_get_duration_returns_null_when_not_started(): void
    {
        $phase = new Phase('test');

        $this->assertNull($phase->getDuration());
    }

    public function test_get_duration_returns_null_when_not_stopped(): void
    {
        $phase = new Phase('test');
        $phase->start();

        $this->assertNull($phase->getDuration());
    }

    public function test_get_memory_usage_returns_int_when_complete(): void
    {
        $phase = new Phase('test');
        $phase->start();
        $phase->stop();

        $this->assertIsInt($phase->getMemoryUsage());
    }

    public function test_get_memory_usage_returns_null_when_not_started(): void
    {
        $phase = new Phase('test');

        $this->assertNull($phase->getMemoryUsage());
    }

    public function test_get_memory_usage_returns_null_when_not_stopped(): void
    {
        $phase = new Phase('test');
        $phase->start();

        $this->assertNull($phase->getMemoryUsage());
    }

    public function test_get_debug_info_returns_expected_structure(): void
    {
        $phase     = new Phase('test');
        $startedAt = $phase->start();
        $stoppedAt = $phase->stop();

        $info = $phase->getDebugInfo();

        $this->assertArrayHasKey('start.time', $info);
        $this->assertArrayHasKey('end.time', $info);
        $this->assertArrayHasKey('duration', $info);
        $this->assertArrayHasKey('memory.usage', $info);
        $this->assertSame($startedAt, $info['start.time']);
        $this->assertSame($stoppedAt, $info['end.time']);
        $this->assertIsFloat($info['duration']);
        $this->assertIsInt($info['memory.usage']);
    }

    public function test_get_debug_info_when_incomplete(): void
    {
        $phase = new Phase('test');

        $info = $phase->getDebugInfo();

        $this->assertNull($info['start.time']);
        $this->assertNull($info['end.time']);
        $this->assertNull($info['duration']);
        $this->assertNull($info['memory.usage']);
    }

    public function test_get_debug_info_is_json_encodable(): void
    {
        $phase = new Phase('test');

        $this->assertIsString(json_encode($phase->getDebugInfo(), JSON_THROW_ON_ERROR));

        $phase->start();

        $this->assertIsString(json_encode($phase->getDebugInfo(), JSON_THROW_ON_ERROR));

        $phase->stop();

        $this->assertIsString(json_encode($phase->getDebugInfo(), JSON_THROW_ON_ERROR));
    }
}
