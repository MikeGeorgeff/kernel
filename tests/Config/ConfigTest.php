<?php

namespace Georgeff\Kernel\Test\Config;

use Georgeff\Kernel\Config\Config;
use Georgeff\Kernel\Config\ConfigInterface;
use Georgeff\Kernel\Exception\ConfigException;
use Georgeff\Kernel\Exception\KernelExceptionInterface;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function test_it_implements_config_interface(): void
    {
        $this->assertInstanceOf(ConfigInterface::class, new Config([]));
    }

    public function test_all_returns_the_full_config_array(): void
    {
        $config = new Config(['db.host' => 'localhost', 'cache.driver' => 'redis']);

        $this->assertSame(['db.host' => 'localhost', 'cache.driver' => 'redis'], $config->all());
    }

    public function test_all_returns_an_empty_array_for_an_empty_config(): void
    {
        $config = new Config([]);

        $this->assertSame([], $config->all());
    }

    public function test_has_returns_true_for_an_existing_key(): void
    {
        $config = new Config(['db.host' => 'localhost']);

        $this->assertTrue($config->has('db.host'));
    }

    public function test_has_returns_false_for_a_missing_key(): void
    {
        $config = new Config([]);

        $this->assertFalse($config->has('db.host'));
    }

    public function test_get_returns_the_value_for_an_existing_key(): void
    {
        $config = new Config(['db.host' => 'localhost']);

        $this->assertSame('localhost', $config->get('db.host'));
    }

    public function test_get_returns_null_by_default_for_a_missing_key(): void
    {
        $config = new Config([]);

        $this->assertNull($config->get('db.host'));
    }

    public function test_get_returns_the_given_default_for_a_missing_key(): void
    {
        $config = new Config([]);

        $this->assertSame('fallback', $config->get('db.host', 'fallback'));
    }

    public function test_get_returns_falsy_values_without_falling_back_to_the_default(): void
    {
        $config = new Config(['feature.enabled' => false, 'retry.count' => 0, 'label' => '']);

        $this->assertFalse($config->get('feature.enabled', true));
        $this->assertSame(0, $config->get('retry.count', 99));
        $this->assertSame('', $config->get('label', 'fallback'));
    }

    public function test_get_returns_the_stored_null_rather_than_the_default_for_an_explicitly_null_key(): void
    {
        $config = new Config(['explicit.null' => null]);

        $this->assertTrue($config->has('explicit.null'));
        $this->assertNull($config->get('explicit.null', 'fallback'));
    }

    public function test_is_empty_returns_true_for_an_empty_config(): void
    {
        $config = new Config([]);

        $this->assertTrue($config->isEmpty());
    }

    public function test_is_empty_returns_false_for_a_non_empty_config(): void
    {
        $config = new Config(['db.host' => 'localhost']);

        $this->assertFalse($config->isEmpty());
    }

    public function test_branch_returns_a_config_wrapping_the_nested_array(): void
    {
        $config = new Config(['db' => ['host' => 'localhost', 'port' => 5432]]);

        $branch = $config->branch('db');

        $this->assertInstanceOf(ConfigInterface::class, $branch);
        $this->assertSame('localhost', $branch->get('host'));
        $this->assertSame(5432, $branch->get('port'));
    }

    public function test_branch_supports_multi_level_chaining(): void
    {
        $config = new Config(['db' => ['read' => ['host' => 'replica']]]);

        $this->assertSame('replica', $config->branch('db')->branch('read')->get('host'));
    }

    public function test_branch_returns_an_empty_config_for_a_missing_key(): void
    {
        $config = new Config([]);

        $branch = $config->branch('db');

        $this->assertTrue($branch->isEmpty());
        $this->assertNull($branch->get('host'));
    }

    public function test_branch_returns_the_same_instance_on_repeated_calls(): void
    {
        $config = new Config(['db' => ['host' => 'localhost']]);

        $this->assertSame($config->branch('db'), $config->branch('db'));
    }

    public function test_branch_throws_for_an_existing_key_with_an_explicitly_null_value(): void
    {
        $config = new Config(['db' => null]);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Branch expects value of [db] to be an array, given: null');

        $config->branch('db');
    }

    public function test_branch_throws_for_a_scalar_value(): void
    {
        $config = new Config(['db' => 'localhost']);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Branch expects value of [db] to be an array, given: string');

        $config->branch('db');
    }

    public function test_branch_throws_for_a_list_array_value(): void
    {
        $config = new Config(['options' => ['a', 'b', 'c']]);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Branch expects array for [options] to have non-numeric keys');

        $config->branch('options');
    }

    public function test_branch_throws_for_a_non_sequential_int_keyed_array_value(): void
    {
        $config = new Config(['options' => [1 => 'a', 3 => 'b']]);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Branch expects array for [options] to have non-numeric keys');

        $config->branch('options');
    }

    public function test_branch_throws_for_numeric_looking_string_keys_that_survive_as_strings(): void
    {
        $config = new Config(['options' => ['00' => 'a', 'valid' => 'b']]);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Branch expects array for [options] to have non-numeric keys');

        $config->branch('options');
    }

    public function test_branch_exception_implements_kernel_exception_interface(): void
    {
        $config = new Config(['db' => 'localhost']);

        try {
            $config->branch('db');
            $this->fail('Expected ConfigException was not thrown.');
        } catch (ConfigException $exception) {
            $this->assertInstanceOf(KernelExceptionInterface::class, $exception);
        }
    }
}
