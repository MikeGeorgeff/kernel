<?php

namespace Georgeff\Kernel\Test\Config;

use Georgeff\Kernel\Config\Config;
use Georgeff\Kernel\Config\ConfigInterface;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function test_it_implements_config_interface(): void
    {
        $this->assertInstanceOf(ConfigInterface::class, new Config([]));
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
}
