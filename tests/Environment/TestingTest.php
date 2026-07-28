<?php

namespace Georgeff\Kernel\Test\Environment;

use Georgeff\Kernel\Environment\AbstractEnvironment;
use Georgeff\Kernel\Environment\Testing;
use PHPUnit\Framework\TestCase;

class TestingTest extends TestCase
{
    public function test_it_extends_abstract_environment(): void
    {
        $this->assertInstanceOf(AbstractEnvironment::class, new Testing());
    }

    public function test_get_value_returns_testing(): void
    {
        $this->assertSame('testing', new Testing()->getValue());
    }
}
