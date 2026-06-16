<?php

namespace Georgeff\Kernel\Test;

use Georgeff\Kernel\Environment;
use PHPUnit\Framework\TestCase;

class EnvironmentTest extends TestCase
{
    public function test_local_case_exists(): void
    {
        $this->assertSame('local', Environment::Local->value);
    }

    public function test_local_can_be_created_from_value(): void
    {
        $this->assertSame(Environment::Local, Environment::from('local'));
    }

    public function test_all_expected_cases_are_present(): void
    {
        $values = array_map(fn(Environment $e) => $e->value, Environment::cases());

        $this->assertContains('production', $values);
        $this->assertContains('staging', $values);
        $this->assertContains('development', $values);
        $this->assertContains('testing', $values);
        $this->assertContains('local', $values);
    }
}
