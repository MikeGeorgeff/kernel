<?php

namespace Georgeff\Kernel\Test\DI\Profile;

use Georgeff\Kernel\Contract\DebuggableInterface;
use Georgeff\Kernel\DI\Profile\ServiceProfile;
use Georgeff\Kernel\Exception\ProfilerException;
use PHPUnit\Framework\TestCase;

class ServiceProfileTest extends TestCase
{
    public function test_it_implements_debuggable_interface(): void
    {
        $service = new ServiceProfile('foo');

        $this->assertInstanceOf(DebuggableInterface::class, $service);
    }

    public function test_id_returns_the_constructor_value(): void
    {
        $service = new ServiceProfile('foo');

        $this->assertSame('foo', $service->id);
    }

    public function test_resolving_returns_self(): void
    {
        $service = new ServiceProfile('foo');

        $this->assertSame($service, $service->resolving());
    }

    public function test_resolved_returns_self(): void
    {
        $service = new ServiceProfile('foo');
        $service->resolving();

        $this->assertSame($service, $service->resolved('instance'));
    }

    public function test_resolved_throws_when_resolving_was_not_started(): void
    {
        $service = new ServiceProfile('foo');

        $this->expectException(ProfilerException::class);
        $this->expectExceptionMessage('Cannot call resolved for a service that has not started resolving');

        $service->resolved('instance');
    }

    public function test_get_debug_info_before_any_resolution(): void
    {
        $service = new ServiceProfile('foo');

        $info = $service->getDebugInfo();

        $this->assertSame(0, $info['count']);
        $this->assertSame(0.0, $info['duration']);
        $this->assertSame(0, $info['memory']);
        $this->assertSame([], $info['resolutions']);
        $this->assertArrayNotHasKey('debug.info', $info);
    }

    public function test_get_debug_info_after_one_resolution(): void
    {
        $service = new ServiceProfile('foo');
        $service->resolving();
        $service->resolved('instance');

        $info = $service->getDebugInfo();

        $this->assertSame(1, $info['count']);
        $this->assertIsFloat($info['duration']);
        $this->assertGreaterThanOrEqual(0.0, $info['duration']);
        $this->assertIsInt($info['memory']);
        $this->assertCount(1, $info['resolutions']);
        $this->assertArrayHasKey('duration', $info['resolutions'][0]);
        $this->assertArrayHasKey('memory.usage', $info['resolutions'][0]);
    }

    public function test_get_debug_info_accumulates_across_multiple_resolutions(): void
    {
        $service = new ServiceProfile('foo');

        $service->resolving();
        $service->resolved('instance-1');

        $service->resolving();
        $service->resolved('instance-2');

        $info = $service->getDebugInfo();

        $this->assertSame(2, $info['count']);
        $this->assertCount(2, $info['resolutions']);
    }

    public function test_get_debug_info_tracks_the_most_recently_resolved_instance(): void
    {
        $debuggable = new class implements DebuggableInterface {
            public function getDebugInfo(): array
            {
                return ['custom' => 'data'];
            }
        };

        $service = new ServiceProfile('foo');
        $service->resolving();
        $service->resolved($debuggable);

        $info = $service->getDebugInfo();

        $this->assertArrayHasKey('debug.info', $info);
        $this->assertSame(['custom' => 'data'], $info['debug.info']);
    }

    public function test_get_debug_info_omits_debug_info_key_for_a_non_debuggable_instance(): void
    {
        $service = new ServiceProfile('foo');
        $service->resolving();
        $service->resolved('a plain string');

        $info = $service->getDebugInfo();

        $this->assertArrayNotHasKey('debug.info', $info);
    }
}
