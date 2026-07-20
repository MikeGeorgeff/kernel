<?php

namespace Georgeff\Kernel\Test\Environment;

use Georgeff\Kernel\Environment\AbstractEnvironment;
use Georgeff\Kernel\Environment\Development;
use PHPUnit\Framework\TestCase;

class DevelopmentTest extends TestCase
{
    public function test_it_extends_abstract_environment(): void
    {
        $this->assertInstanceOf(AbstractEnvironment::class, new Development());
    }

    public function test_get_value_returns_development(): void
    {
        $this->assertSame('development', new Development()->getValue());
    }
}
