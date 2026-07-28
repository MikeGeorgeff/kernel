<?php

namespace Georgeff\Kernel\Test\DI\Profile;

use Georgeff\Kernel\Contract\DebuggableInterface;
use Georgeff\Kernel\DI\Profile\ServiceResolutionProfile;
use Georgeff\Kernel\Exception\ProfilerException;
use PHPUnit\Framework\TestCase;

class ServiceResolutionProfileTest extends TestCase
{
    public function test_it_implements_debuggable_interface(): void
    {
        $profile = new ServiceResolutionProfile([]);

        $this->assertInstanceOf(DebuggableInterface::class, $profile);
    }

    public function test_get_debug_info_lists_every_definition_as_unresolved_initially(): void
    {
        $profile = new ServiceResolutionProfile([
            'foo' => ['shared' => false, 'aliases' => [], 'tags' => []],
            'bar' => ['shared' => false, 'aliases' => [], 'tags' => []],
        ]);

        $info = $profile->getDebugInfo();

        $this->assertSame([], $info['resolved']);
        $this->assertSame(['foo', 'bar'], $info['unresolved']);
    }

    public function test_resolved_throws_when_resolving_was_not_started(): void
    {
        $profile = new ServiceResolutionProfile([
            'foo' => ['shared' => false, 'aliases' => [], 'tags' => []],
        ]);

        $this->expectException(ProfilerException::class);
        $this->expectExceptionMessage('Service ID [foo] has not started resolving');

        $profile->resolved('foo', 'instance');
    }

    public function test_resolving_then_resolved_moves_a_service_out_of_unresolved(): void
    {
        $profile = new ServiceResolutionProfile([
            'foo' => ['shared' => false, 'aliases' => [], 'tags' => []],
        ]);

        $profile->resolving('foo');
        $profile->resolved('foo', 'instance');

        $info = $profile->getDebugInfo();

        $this->assertArrayHasKey('foo', $info['resolved']);
        $this->assertSame([], $info['unresolved']);
    }

    public function test_resolved_service_debug_info_has_a_resolution_count_of_one(): void
    {
        $profile = new ServiceResolutionProfile([
            'foo' => ['shared' => false, 'aliases' => [], 'tags' => []],
        ]);

        $profile->resolving('foo');
        $profile->resolved('foo', 'instance');

        $info = $profile->getDebugInfo();

        $this->assertSame(1, $info['resolved']['foo']['count']);
    }

    public function test_resolving_and_resolved_translate_an_alias_to_the_canonical_id(): void
    {
        $profile = new ServiceResolutionProfile([
            'foo' => ['shared' => false, 'aliases' => ['foo.alias'], 'tags' => []],
        ]);

        $profile->resolving('foo.alias');
        $profile->resolved('foo.alias', 'instance');

        $info = $profile->getDebugInfo();

        $this->assertArrayHasKey('foo', $info['resolved']);
        $this->assertArrayNotHasKey('foo.alias', $info['resolved']);
        $this->assertNotContains('foo.alias', $info['unresolved']);
    }

    public function test_resolving_via_alias_and_resolved_via_canonical_id_track_the_same_service(): void
    {
        $profile = new ServiceResolutionProfile([
            'foo' => ['shared' => false, 'aliases' => ['foo.alias'], 'tags' => []],
        ]);

        $profile->resolving('foo.alias');
        $profile->resolved('foo', 'instance');

        $info = $profile->getDebugInfo();

        $this->assertArrayHasKey('foo', $info['resolved']);
    }

    public function test_resolving_a_shared_service_a_second_time_is_a_no_op(): void
    {
        $profile = new ServiceResolutionProfile([
            'foo' => ['shared' => true, 'aliases' => [], 'tags' => []],
        ]);

        $profile->resolving('foo');
        $profile->resolved('foo', 'instance');

        // Simulates a cache-hit get() call: onResolving fires again, onResolved does not.
        $profile->resolving('foo');

        $info = $profile->getDebugInfo();

        $this->assertSame(1, $info['resolved']['foo']['count']);
    }

    public function test_resolving_a_non_shared_service_multiple_times_accumulates_resolutions(): void
    {
        $profile = new ServiceResolutionProfile([
            'foo' => ['shared' => false, 'aliases' => [], 'tags' => []],
        ]);

        $profile->resolving('foo');
        $profile->resolved('foo', 'instance-1');

        $profile->resolving('foo');
        $profile->resolved('foo', 'instance-2');

        $info = $profile->getDebugInfo();

        $this->assertSame(2, $info['resolved']['foo']['count']);
    }

    public function test_multiple_services_are_tracked_independently(): void
    {
        $profile = new ServiceResolutionProfile([
            'foo' => ['shared' => false, 'aliases' => [], 'tags' => []],
            'bar' => ['shared' => false, 'aliases' => [], 'tags' => []],
        ]);

        $profile->resolving('foo');
        $profile->resolved('foo', 'instance');

        $info = $profile->getDebugInfo();

        $this->assertArrayHasKey('foo', $info['resolved']);
        $this->assertContains('bar', $info['unresolved']);
        $this->assertNotContains('foo', $info['unresolved']);
    }
}
