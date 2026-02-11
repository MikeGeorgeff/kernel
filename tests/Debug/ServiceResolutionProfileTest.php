<?php

namespace Georgeff\Kernel\Test\Debug;

use Georgeff\Kernel\Debug\DebuggableInterface;
use Georgeff\Kernel\Debug\ResolvedService;
use Georgeff\Kernel\Debug\ServiceResolutionProfile;
use PHPUnit\Framework\TestCase;

class ServiceResolutionProfileTest extends TestCase
{
    public function test_it_implements_debuggable_interface(): void
    {
        $profile = new ServiceResolutionProfile([]);

        $this->assertInstanceOf(DebuggableInterface::class, $profile);
    }

    public function test_all_services_start_unresolved(): void
    {
        $profile = new ServiceResolutionProfile([
            'foo' => ['factory' => fn() => 'foo', 'shared' => true, 'aliases' => []],
            'bar' => ['factory' => fn() => 'bar', 'shared' => true, 'aliases' => []],
        ]);

        $this->assertSame(['foo', 'bar'], $profile->getUnresolvedServices());
        $this->assertSame([], $profile->getResolvedServices());
    }

    public function test_resolve_moves_service_from_unresolved_to_resolved(): void
    {
        $profile = new ServiceResolutionProfile([
            'foo' => ['factory' => fn() => 'foo', 'shared' => true, 'aliases' => []],
            'bar' => ['factory' => fn() => 'bar', 'shared' => true, 'aliases' => []],
        ]);

        $profile->resolve('foo', 0.001);

        $this->assertSame(['bar'], $profile->getUnresolvedServices());
        $this->assertArrayHasKey('foo', $profile->getResolvedServices());
    }

    public function test_resolve_returns_resolved_service(): void
    {
        $profile = new ServiceResolutionProfile([
            'foo' => ['factory' => fn() => 'foo', 'shared' => true, 'aliases' => []],
        ]);

        $resolved = $profile->resolve('foo', 0.001);

        $this->assertInstanceOf(ResolvedService::class, $resolved);
    }

    public function test_resolve_increments_count_and_adds_time(): void
    {
        $profile = new ServiceResolutionProfile([
            'foo' => ['factory' => fn() => 'foo', 'shared' => true, 'aliases' => []],
        ]);

        $profile->resolve('foo', 0.001);

        $resolved = $profile->getResolvedServices();

        $this->assertSame(1, $resolved['foo']['resolutionCount']);
        $this->assertSame(0.001, $resolved['foo']['totalResolutionTime']);
    }

    public function test_resolve_same_service_multiple_times_accumulates(): void
    {
        $profile = new ServiceResolutionProfile([
            'foo' => ['factory' => fn() => 'foo', 'shared' => false, 'aliases' => []],
        ]);

        $profile->resolve('foo', 0.001);
        $profile->resolve('foo', 0.002);
        $profile->resolve('foo', 0.003);

        $resolved = $profile->getResolvedServices();

        $this->assertSame(3, $resolved['foo']['resolutionCount']);
        $this->assertSame(0.006, $resolved['foo']['totalResolutionTime']);
    }

    public function test_resolve_via_alias(): void
    {
        $profile = new ServiceResolutionProfile([
            'foo' => ['factory' => fn() => 'foo', 'shared' => true, 'aliases' => ['FooAlias']],
        ]);

        $profile->resolve('FooAlias', 0.001);

        $this->assertSame([], $profile->getUnresolvedServices());
        $this->assertArrayHasKey('foo', $profile->getResolvedServices());
    }

    public function test_resolve_via_alias_and_id_tracks_same_service(): void
    {
        $profile = new ServiceResolutionProfile([
            'foo' => ['factory' => fn() => 'foo', 'shared' => true, 'aliases' => ['FooAlias']],
        ]);

        $profile->resolve('foo', 0.001);
        $profile->resolve('FooAlias', 0.002);

        $resolved = $profile->getResolvedServices();

        $this->assertSame(2, $resolved['foo']['resolutionCount']);
    }

    public function test_get_debug_info_returns_expected_structure(): void
    {
        $profile = new ServiceResolutionProfile([
            'foo' => ['factory' => fn() => 'foo', 'shared' => true, 'aliases' => []],
            'bar' => ['factory' => fn() => 'bar', 'shared' => true, 'aliases' => []],
        ]);

        $profile->resolve('foo', 0.001);

        $info = $profile->getDebugInfo();

        $this->assertArrayHasKey('resolved', $info);
        $this->assertArrayHasKey('unresolved', $info);
        $this->assertArrayHasKey('foo', $info['resolved']);
        $this->assertSame(['bar'], $info['unresolved']);
    }

    public function test_empty_definitions(): void
    {
        $profile = new ServiceResolutionProfile([]);

        $this->assertSame([], $profile->getResolvedServices());
        $this->assertSame([], $profile->getUnresolvedServices());
    }
}
