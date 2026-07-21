<?php

namespace Georgeff\Kernel\Test\Profiler;

use Georgeff\Kernel\Contract\DebuggableInterface;
use Georgeff\Kernel\Profiler\Profile;
use PHPUnit\Framework\TestCase;

class ProfileTest extends TestCase
{
    public function test_it_implements_debuggable_interface(): void
    {
        $profile = new Profile();

        $this->assertInstanceOf(DebuggableInterface::class, $profile);
    }

    public function test_start_returns_float(): void
    {
        $profile = new Profile();

        $this->assertIsFloat($profile->start());
    }

    public function test_stop_returns_float(): void
    {
        $profile = new Profile();
        $profile->start();

        $this->assertIsFloat($profile->stop());
    }

    public function test_get_overall_duration_returns_positive_float(): void
    {
        $profile = new Profile();
        $profile->start();
        $profile->stop();

        $this->assertIsFloat($profile->getOverallDuration());
        $this->assertGreaterThanOrEqual(0.0, $profile->getOverallDuration());
    }

    public function test_get_overall_duration_returns_negative_infinity_when_not_started(): void
    {
        $profile = new Profile();

        $this->assertSame(-INF, $profile->getOverallDuration());
    }

    public function test_get_overall_duration_returns_negative_infinity_when_not_stopped(): void
    {
        $profile = new Profile();
        $profile->start();

        $this->assertSame(-INF, $profile->getOverallDuration());
    }

    public function test_get_start_time_returns_the_value_from_start(): void
    {
        $profile   = new Profile();
        $startedAt = $profile->start();

        $this->assertSame($startedAt, $profile->getStartTime());
    }

    public function test_get_start_time_returns_negative_infinity_when_not_started(): void
    {
        $profile = new Profile();

        $this->assertSame(-INF, $profile->getStartTime());
    }

    public function test_start_phase_returns_float(): void
    {
        $profile = new Profile();

        $this->assertIsFloat($profile->startPhase('test'));
    }

    public function test_stop_phase_returns_float(): void
    {
        $profile = new Profile();
        $profile->startPhase('test');

        $this->assertIsFloat($profile->stopPhase('test'));
    }

    public function test_get_phase_duration_returns_positive_float(): void
    {
        $profile = new Profile();
        $profile->startPhase('test');
        $profile->stopPhase('test');

        $this->assertIsFloat($profile->getPhaseDuration('test'));
        $this->assertGreaterThanOrEqual(0.0, $profile->getPhaseDuration('test'));
    }

    public function test_get_phase_duration_returns_negative_infinity_for_unknown_phase(): void
    {
        $profile = new Profile();

        $this->assertSame(-INF, $profile->getPhaseDuration('unknown'));
    }

    public function test_get_phase_duration_returns_negative_infinity_when_not_stopped(): void
    {
        $profile = new Profile();
        $profile->startPhase('test');

        $this->assertSame(-INF, $profile->getPhaseDuration('test'));
    }

    public function test_get_phase_duration_returns_negative_infinity_when_only_stopped(): void
    {
        $profile = new Profile();
        $profile->stopPhase('test');

        $this->assertSame(-INF, $profile->getPhaseDuration('test'));
    }

    public function test_multiple_phases_tracked_independently(): void
    {
        $profile = new Profile();

        $profile->startPhase('first');
        $profile->stopPhase('first');

        $profile->startPhase('second');
        $profile->stopPhase('second');

        $this->assertGreaterThanOrEqual(0.0, $profile->getPhaseDuration('first'));
        $this->assertGreaterThanOrEqual(0.0, $profile->getPhaseDuration('second'));
    }

    public function test_get_debug_info_returns_expected_structure(): void
    {
        $profile = new Profile();
        $profile->start();
        $profile->startPhase('testPhase');
        $profile->stopPhase('testPhase');
        $profile->stop();

        $info = $profile->getDebugInfo();

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
        $profile = new Profile();
        $profile->start();
        $profile->startPhase('testPhase');
        $profile->stopPhase('testPhase');

        $info = $profile->getDebugInfo();

        $this->assertNull($info['duration']);
        $this->assertIsFloat($info['phases']['testPhase']['duration']);
    }

    public function test_get_debug_info_without_phases(): void
    {
        $profile = new Profile();
        $profile->start();
        $profile->stop();

        $info = $profile->getDebugInfo();

        $this->assertArrayNotHasKey('phases', $info);
    }

    public function test_get_debug_info_with_no_timers(): void
    {
        $profile = new Profile();

        $info = $profile->getDebugInfo();

        $this->assertNull($info['start.time']);
        $this->assertNull($info['end.time']);
        $this->assertNull($info['duration']);
    }

    public function test_get_debug_info_is_json_encodable(): void
    {
        $profile = new Profile();

        $this->assertIsString(json_encode($profile->getDebugInfo(), JSON_THROW_ON_ERROR));

        $profile->start();
        $profile->startPhase('test');

        $this->assertIsString(json_encode($profile->getDebugInfo(), JSON_THROW_ON_ERROR));

        $profile->stopPhase('test');
        $profile->stop();

        $this->assertIsString(json_encode($profile->getDebugInfo(), JSON_THROW_ON_ERROR));
    }
}
