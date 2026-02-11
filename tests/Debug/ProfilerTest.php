<?php

namespace Georgeff\Kernel\Test\Debug;

use Georgeff\Kernel\Debug\DebuggableInterface;
use Georgeff\Kernel\Debug\Profiler;
use PHPUnit\Framework\TestCase;

class ProfilerTest extends TestCase
{
    public function test_it_implements_debuggable_interface(): void
    {
        $profiler = new Profiler();

        $this->assertInstanceOf(DebuggableInterface::class, $profiler);
    }

    public function test_start_returns_float(): void
    {
        $profiler = new Profiler();

        $this->assertIsFloat($profiler->start());
    }

    public function test_stop_returns_float(): void
    {
        $profiler = new Profiler();
        $profiler->start();

        $this->assertIsFloat($profiler->stop());
    }

    public function test_get_overall_duration_returns_positive_float(): void
    {
        $profiler = new Profiler();
        $profiler->start();
        $profiler->stop();

        $this->assertIsFloat($profiler->getOverallDuration());
        $this->assertGreaterThanOrEqual(0.0, $profiler->getOverallDuration());
    }

    public function test_get_overall_duration_returns_negative_infinity_when_not_started(): void
    {
        $profiler = new Profiler();

        $this->assertSame(-INF, $profiler->getOverallDuration());
    }

    public function test_get_overall_duration_returns_negative_infinity_when_not_stopped(): void
    {
        $profiler = new Profiler();
        $profiler->start();

        $this->assertSame(-INF, $profiler->getOverallDuration());
    }

    public function test_start_phase_returns_float(): void
    {
        $profiler = new Profiler();

        $this->assertIsFloat($profiler->startPhase('test'));
    }

    public function test_stop_phase_returns_float(): void
    {
        $profiler = new Profiler();
        $profiler->startPhase('test');

        $this->assertIsFloat($profiler->stopPhase('test'));
    }

    public function test_get_phase_duration_returns_positive_float(): void
    {
        $profiler = new Profiler();
        $profiler->startPhase('test');
        $profiler->stopPhase('test');

        $this->assertIsFloat($profiler->getPhaseDuration('test'));
        $this->assertGreaterThanOrEqual(0.0, $profiler->getPhaseDuration('test'));
    }

    public function test_get_phase_duration_returns_negative_infinity_for_unknown_phase(): void
    {
        $profiler = new Profiler();

        $this->assertSame(-INF, $profiler->getPhaseDuration('unknown'));
    }

    public function test_get_phase_duration_returns_negative_infinity_when_not_stopped(): void
    {
        $profiler = new Profiler();
        $profiler->startPhase('test');

        $this->assertSame(-INF, $profiler->getPhaseDuration('test'));
    }

    public function test_get_phase_duration_returns_negative_infinity_when_only_stopped(): void
    {
        $profiler = new Profiler();
        $profiler->stopPhase('test');

        $this->assertSame(-INF, $profiler->getPhaseDuration('test'));
    }

    public function test_multiple_phases_tracked_independently(): void
    {
        $profiler = new Profiler();

        $profiler->startPhase('first');
        $profiler->stopPhase('first');

        $profiler->startPhase('second');
        $profiler->stopPhase('second');

        $this->assertGreaterThanOrEqual(0.0, $profiler->getPhaseDuration('first'));
        $this->assertGreaterThanOrEqual(0.0, $profiler->getPhaseDuration('second'));
    }

    public function test_get_debug_info_returns_expected_structure(): void
    {
        $profiler = new Profiler();
        $profiler->start();
        $profiler->startPhase('testPhase');
        $profiler->stopPhase('testPhase');
        $profiler->stop();

        $info = $profiler->getDebugInfo();

        $this->assertArrayHasKey('start.time', $info);
        $this->assertArrayHasKey('end.time', $info);
        $this->assertArrayHasKey('duration', $info);
        $this->assertArrayHasKey('phases', $info);
        $this->assertArrayHasKey('testPhase', $info['phases']);
        $this->assertArrayHasKey('start.time', $info['phases']['testPhase']);
        $this->assertArrayHasKey('end.time', $info['phases']['testPhase']);
        $this->assertArrayHasKey('duration', $info['phases']['testPhase']);
        $this->assertIsFloat($info['duration']);
        $this->assertIsFloat($info['phases']['testPhase']['duration']);
    }

    public function test_get_debug_info_with_incomplete_global_timer(): void
    {
        $profiler = new Profiler();
        $profiler->start();
        $profiler->startPhase('testPhase');
        $profiler->stopPhase('testPhase');

        $info = $profiler->getDebugInfo();

        $this->assertSame(-INF, $info['duration']);
        $this->assertIsFloat($info['phases']['testPhase']['duration']);
    }

    public function test_get_debug_info_without_phases(): void
    {
        $profiler = new Profiler();
        $profiler->start();
        $profiler->stop();

        $info = $profiler->getDebugInfo();

        $this->assertArrayNotHasKey('phases', $info);
    }

    public function test_get_debug_info_with_no_timers(): void
    {
        $profiler = new Profiler();

        $info = $profiler->getDebugInfo();

        $this->assertSame(-INF, $info['start.time']);
        $this->assertSame(-INF, $info['end.time']);
        $this->assertSame(-INF, $info['duration']);
    }
}
