<?php

namespace Georgeff\Kernel\Test\Storage;

use Georgeff\Kernel\Contract\ResettableInterface;
use Georgeff\Kernel\Debug\DebuggableInterface;
use Georgeff\Kernel\Storage\Cache;
use PHPUnit\Framework\TestCase;

class CacheTest extends TestCase
{
    public function test_it_implements_debuggable_interface(): void
    {
        $this->assertInstanceOf(DebuggableInterface::class, new Cache());
    }

    public function test_it_implements_resettable_interface(): void
    {
        $this->assertInstanceOf(ResettableInterface::class, new Cache());
    }

    public function test_has_returns_false_for_a_missing_key(): void
    {
        $cache = new Cache();

        $this->assertFalse($cache->has('foo'));
    }

    public function test_has_returns_true_after_set(): void
    {
        $cache = new Cache();
        $cache->set('foo', 'bar');

        $this->assertTrue($cache->has('foo'));
    }

    public function test_set_and_get_round_trip_a_string(): void
    {
        $cache = new Cache();
        $cache->set('foo', 'bar');

        $this->assertSame('bar', $cache->get('foo'));
    }

    public function test_set_and_get_round_trip_an_integer(): void
    {
        $cache = new Cache();
        $cache->set('foo', 42);

        $this->assertSame(42, $cache->get('foo'));
    }

    public function test_set_and_get_round_trip_a_float(): void
    {
        $cache = new Cache();
        $cache->set('foo', 4.2);

        $this->assertSame(4.2, $cache->get('foo'));
    }

    public function test_set_and_get_round_trip_a_boolean(): void
    {
        $cache = new Cache();
        $cache->set('foo', false);

        $this->assertFalse($cache->get('foo'));
    }

    public function test_set_and_get_round_trip_an_array(): void
    {
        $cache = new Cache();
        $cache->set('foo', ['a' => 1]);

        $this->assertSame(['a' => 1], $cache->get('foo'));
    }

    public function test_set_and_get_round_trip_null(): void
    {
        $cache = new Cache();
        $cache->set('foo', null);

        $this->assertTrue($cache->has('foo'));
        $this->assertNull($cache->get('foo'));
    }

    public function test_set_overwrites_an_existing_key(): void
    {
        $cache = new Cache();
        $cache->set('foo', 'first');
        $cache->set('foo', 'second');

        $this->assertSame('second', $cache->get('foo'));
    }

    public function test_get_throws_for_a_missing_key(): void
    {
        $cache = new Cache();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cache key [foo] was not found');

        $cache->get('foo');
    }

    public function test_get_string_returns_a_string_value(): void
    {
        $cache = new Cache();
        $cache->set('foo', 'bar');

        $this->assertSame('bar', $cache->getString('foo'));
    }

    public function test_get_string_throws_for_a_non_string_value(): void
    {
        $cache = new Cache();
        $cache->set('foo', 42);

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Invalid type for cache key [foo]. Expected [string] got [int]');

        $cache->getString('foo');
    }

    public function test_get_integer_returns_an_integer_value(): void
    {
        $cache = new Cache();
        $cache->set('foo', 42);

        $this->assertSame(42, $cache->getInteger('foo'));
    }

    public function test_get_integer_throws_for_a_non_integer_value(): void
    {
        $cache = new Cache();
        $cache->set('foo', 'bar');

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Invalid type for cache key [foo]. Expected [integer] got [string]');

        $cache->getInteger('foo');
    }

    public function test_get_float_returns_a_float_value(): void
    {
        $cache = new Cache();
        $cache->set('foo', 4.2);

        $this->assertSame(4.2, $cache->getFloat('foo'));
    }

    public function test_get_float_throws_for_a_non_float_value(): void
    {
        $cache = new Cache();
        $cache->set('foo', 42);

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Invalid type for cache key [foo]. Expected [float] got [int]');

        $cache->getFloat('foo');
    }

    public function test_get_boolean_returns_a_boolean_value(): void
    {
        $cache = new Cache();
        $cache->set('foo', true);

        $this->assertTrue($cache->getBoolean('foo'));
    }

    public function test_get_boolean_throws_for_a_non_boolean_value(): void
    {
        $cache = new Cache();
        $cache->set('foo', 'bar');

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Invalid type for cache key [foo]. Expected [boolean] got [string]');

        $cache->getBoolean('foo');
    }

    public function test_get_array_returns_an_array_value(): void
    {
        $cache = new Cache();
        $cache->set('foo', ['a' => 1]);

        $this->assertSame(['a' => 1], $cache->getArray('foo'));
    }

    public function test_get_array_throws_for_a_non_array_value(): void
    {
        $cache = new Cache();
        $cache->set('foo', 'bar');

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Invalid type for cache key [foo]. Expected [array] got [string]');

        $cache->getArray('foo');
    }

    public function test_merge_array_overwrites_overlapping_keys_with_the_new_values(): void
    {
        $cache = new Cache();
        $cache->set('foo', ['a' => 'old-a', 'b' => 'old-b']);

        $cache->mergeArray('foo', ['a' => 'new-a', 'c' => 'new-c']);

        $this->assertEqualsCanonicalizing(
            ['a' => 'new-a', 'b' => 'old-b', 'c' => 'new-c'],
            $cache->getArray('foo'),
        );
    }

    public function test_merge_array_does_not_throw_for_a_missing_key(): void
    {
        $cache = new Cache();

        $cache->mergeArray('foo', ['a' => 1]);

        $this->assertSame(['a' => 1], $cache->getArray('foo'));
    }

    public function test_remove_deletes_a_key(): void
    {
        $cache = new Cache();
        $cache->set('foo', 'bar');
        $cache->remove('foo');

        $this->assertFalse($cache->has('foo'));
    }

    public function test_remove_is_a_no_op_for_a_missing_key(): void
    {
        $cache = new Cache();

        $cache->remove('foo');

        $this->assertFalse($cache->has('foo'));
    }

    public function test_all_returns_every_stored_key(): void
    {
        $cache = new Cache();
        $cache->set('foo', 'bar');
        $cache->set('baz', 42);

        $this->assertSame(['foo' => 'bar', 'baz' => 42], $cache->all());
    }

    public function test_all_returns_an_empty_array_when_nothing_is_stored(): void
    {
        $cache = new Cache();

        $this->assertSame([], $cache->all());
    }

    public function test_get_debug_info_returns_the_same_data_as_all(): void
    {
        $cache = new Cache();
        $cache->set('foo', 'bar');

        $this->assertSame($cache->all(), $cache->getDebugInfo());
    }

    public function test_reset_clears_every_stored_key(): void
    {
        $cache = new Cache();
        $cache->set('foo', 'bar');
        $cache->set('baz', 42);

        $cache->reset();

        $this->assertSame([], $cache->all());
    }
}
