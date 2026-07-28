<?php

namespace Georgeff\Kernel\Test\Environment;

use Georgeff\Kernel\Environment\AbstractEnvironment;
use Georgeff\Kernel\Environment\Staging;
use PHPUnit\Framework\TestCase;

class StagingTest extends TestCase
{
    public function test_it_extends_abstract_environment(): void
    {
        $this->assertInstanceOf(AbstractEnvironment::class, new Staging());
    }

    public function test_get_value_returns_staging(): void
    {
        $this->assertSame('staging', new Staging()->getValue());
    }
}
