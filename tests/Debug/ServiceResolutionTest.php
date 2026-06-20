<?php

namespace Georgeff\Kernel\Test\Debug;

use Georgeff\Kernel\Debug\DebuggableInterface;
use Georgeff\Kernel\Debug\ResolvedService;
use Georgeff\Kernel\Debug\ServiceResolution;
use PHPUnit\Framework\TestCase;

class ServiceResolutionTest extends TestCase
{
    public function test_it_implements_debuggable_interface(): void
    {
        $resolution = new ServiceResolution([]);

        $this->assertInstanceOf(DebuggableInterface::class, $resolution);
    }

    public function test_all_services_start_unresolved(): void
    {
        $resolution = new ServiceResolution([
            'foo' => ['factory' => fn() => 'foo', 'shared' => true, 'aliases' => []],
            'bar' => ['factory' => fn() => 'bar', 'shared' => true, 'aliases' => []],
        ]);

        $info = $resolution->getDebugInfo();

        $this->assertSame(['foo', 'bar'], $info['unresolved']);
        $this->assertSame([], $info['resolved']);
    }

    public function test_resolve_moves_service_from_unresolved_to_resolved(): void
    {
        $resolution = new ServiceResolution([
            'foo' => ['factory' => fn() => 'foo', 'shared' => true, 'aliases' => []],
            'bar' => ['factory' => fn() => 'bar', 'shared' => true, 'aliases' => []],
        ]);

        $resolution->resolve('foo', new \stdClass());

        $info = $resolution->getDebugInfo();

        $this->assertSame(['bar'], $info['unresolved']);
        $this->assertArrayHasKey('foo', $info['resolved']);
    }

    public function test_resolve_returns_resolved_service(): void
    {
        $resolution = new ServiceResolution([
            'foo' => ['factory' => fn() => 'foo', 'shared' => true, 'aliases' => []],
        ]);

        $result = $resolution->resolve('foo', new \stdClass());

        $this->assertInstanceOf(ResolvedService::class, $result);
    }

    public function test_resolve_increments_count(): void
    {
        $resolution = new ServiceResolution([
            'foo' => ['factory' => fn() => 'foo', 'shared' => false, 'aliases' => []],
        ]);

        $resolution->resolve('foo', new \stdClass());
        $resolution->resolve('foo', new \stdClass());
        $resolution->resolve('foo', new \stdClass());

        $info = $resolution->getDebugInfo();

        $this->assertSame(3, $info['resolved']['foo']['resolutionCount']);
    }

    public function test_resolve_via_alias(): void
    {
        $resolution = new ServiceResolution([
            'foo' => ['factory' => fn() => 'foo', 'shared' => true, 'aliases' => ['FooAlias']],
        ]);

        $resolution->resolve('FooAlias', new \stdClass());

        $info = $resolution->getDebugInfo();

        $this->assertSame([], $info['unresolved']);
        $this->assertArrayHasKey('foo', $info['resolved']);
    }

    public function test_resolve_via_alias_and_id_tracks_same_service(): void
    {
        $resolution = new ServiceResolution([
            'foo' => ['factory' => fn() => 'foo', 'shared' => true, 'aliases' => ['FooAlias']],
        ]);

        $resolution->resolve('foo', new \stdClass());
        $resolution->resolve('FooAlias', new \stdClass());

        $info = $resolution->getDebugInfo();

        $this->assertSame(2, $info['resolved']['foo']['resolutionCount']);
    }

    public function test_get_debug_info_returns_expected_structure(): void
    {
        $resolution = new ServiceResolution([
            'foo' => ['factory' => fn() => 'foo', 'shared' => true, 'aliases' => []],
            'bar' => ['factory' => fn() => 'bar', 'shared' => true, 'aliases' => []],
        ]);

        $resolution->resolve('foo', new \stdClass());

        $info = $resolution->getDebugInfo();

        $this->assertArrayHasKey('resolved', $info);
        $this->assertArrayHasKey('unresolved', $info);
        $this->assertArrayHasKey('foo', $info['resolved']);
        $this->assertSame(['bar'], $info['unresolved']);
    }

    public function test_multiple_unresolved_services_all_appear_in_list(): void
    {
        $resolution = new ServiceResolution([
            'foo' => ['factory' => fn() => 'foo', 'shared' => true, 'aliases' => []],
            'bar' => ['factory' => fn() => 'bar', 'shared' => true, 'aliases' => []],
            'baz' => ['factory' => fn() => 'baz', 'shared' => true, 'aliases' => []],
        ]);

        $resolution->resolve('foo', new \stdClass());

        $info = $resolution->getDebugInfo();

        $this->assertSame(['bar', 'baz'], $info['unresolved']);
    }

    public function test_empty_definitions(): void
    {
        $resolution = new ServiceResolution([]);

        $info = $resolution->getDebugInfo();

        $this->assertSame([], $info['resolved']);
        $this->assertSame([], $info['unresolved']);
    }

    public function test_resolved_service_debug_info_included_when_debuggable(): void
    {
        $inner = new class implements DebuggableInterface {
            public function getDebugInfo(): array { return ['custom' => 'info']; }
        };

        $resolution = new ServiceResolution([
            'foo' => ['factory' => fn() => $inner, 'shared' => true, 'aliases' => []],
        ]);

        $resolution->resolve('foo', $inner);

        $info = $resolution->getDebugInfo();

        $this->assertArrayHasKey('debugInfo', $info['resolved']['foo']);
        $this->assertSame(['custom' => 'info'], $info['resolved']['foo']['debugInfo']);
    }
}
