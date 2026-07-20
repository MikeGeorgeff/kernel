<?php

namespace Georgeff\Kernel\Test\Environment;

use Georgeff\Kernel\Environment\AbstractEnvironment;
use Georgeff\Kernel\Environment\Local;
use PHPUnit\Framework\TestCase;

class LocalTest extends TestCase
{
    public function test_it_extends_abstract_environment(): void
    {
        $this->assertInstanceOf(AbstractEnvironment::class, new Local());
    }

    public function test_get_value_returns_local(): void
    {
        $this->assertSame('local', new Local()->getValue());
    }
}
