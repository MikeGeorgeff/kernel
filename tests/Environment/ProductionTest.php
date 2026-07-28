<?php

namespace Georgeff\Kernel\Test\Environment;

use Georgeff\Kernel\Environment\AbstractEnvironment;
use Georgeff\Kernel\Environment\Production;
use PHPUnit\Framework\TestCase;

class ProductionTest extends TestCase
{
    public function test_it_extends_abstract_environment(): void
    {
        $this->assertInstanceOf(AbstractEnvironment::class, new Production());
    }

    public function test_get_value_returns_production(): void
    {
        $this->assertSame('production', new Production()->getValue());
    }
}
