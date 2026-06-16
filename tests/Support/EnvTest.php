<?php

namespace Georgeff\Kernel\Test\Support;

use Georgeff\Kernel\Support\Env;
use PHPUnit\Framework\TestCase;

class EnvTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('TEST_ENV_VAR');
    }

    public function test_returns_default_when_var_is_not_set(): void
    {
        $this->assertNull(Env::get('TEST_ENV_VAR'));
    }

    public function test_returns_custom_default_when_var_is_not_set(): void
    {
        $this->assertSame('fallback', Env::get('TEST_ENV_VAR', 'fallback'));
    }

    public function test_returns_raw_string(): void
    {
        putenv('TEST_ENV_VAR=hello');

        $this->assertSame('hello', Env::get('TEST_ENV_VAR'));
    }

    public function test_coerces_true_variants(): void
    {
        foreach (['true', 'TRUE', '(true)'] as $value) {
            putenv("TEST_ENV_VAR={$value}");

            $this->assertTrue(Env::get('TEST_ENV_VAR'), "Expected true for '{$value}'");
        }
    }

    public function test_coerces_false_variants(): void
    {
        foreach (['false', 'FALSE', '(false)'] as $value) {
            putenv("TEST_ENV_VAR={$value}");

            $this->assertFalse(Env::get('TEST_ENV_VAR'), "Expected false for '{$value}'");
        }
    }

    public function test_coerces_null_variants(): void
    {
        foreach (['null', 'NULL', '(null)'] as $value) {
            putenv("TEST_ENV_VAR={$value}");

            $this->assertNull(Env::get('TEST_ENV_VAR'), "Expected null for '{$value}'");
        }
    }

    public function test_coerces_json_object_to_array(): void
    {
        putenv('TEST_ENV_VAR={"key":"value"}');

        $this->assertSame(['key' => 'value'], Env::get('TEST_ENV_VAR'));
    }

    public function test_coerces_json_array_to_array(): void
    {
        putenv('TEST_ENV_VAR=["a","b","c"]');

        $this->assertSame(['a', 'b', 'c'], Env::get('TEST_ENV_VAR'));
    }

    public function test_coerces_empty_json_array_to_array(): void
    {
        putenv('TEST_ENV_VAR=[]');

        $this->assertSame([], Env::get('TEST_ENV_VAR'));
    }

    public function test_returns_raw_string_for_invalid_json(): void
    {
        putenv('TEST_ENV_VAR={not valid json}');

        $this->assertSame('{not valid json}', Env::get('TEST_ENV_VAR'));
    }

    public function test_does_not_coerce_numeric_strings(): void
    {
        putenv('TEST_ENV_VAR=3306');

        $result = Env::get('TEST_ENV_VAR');

        $this->assertIsString($result);
        $this->assertSame('3306', $result);
    }
}
