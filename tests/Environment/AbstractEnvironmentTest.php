<?php

namespace Georgeff\Kernel\Test\Environment;

use Georgeff\Kernel\Contract\EnvironmentInterface;
use Georgeff\Kernel\Environment\AbstractEnvironment;
use PHPUnit\Framework\TestCase;

class AbstractEnvironmentTest extends TestCase
{
    public function test_it_implements_environment_interface(): void
    {
        $environment = $this->createEnvironment('production');

        $this->assertInstanceOf(EnvironmentInterface::class, $environment);
    }

    public function test_is_returns_true_when_value_matches(): void
    {
        $environment = $this->createEnvironment('production');

        $this->assertTrue($environment->is('production'));
    }

    public function test_is_returns_false_when_value_does_not_match(): void
    {
        $environment = $this->createEnvironment('production');

        $this->assertFalse($environment->is('staging'));
    }

    public function test_is_returns_true_when_any_of_multiple_values_match(): void
    {
        $environment = $this->createEnvironment('staging');

        $this->assertTrue($environment->is('production', 'staging'));
    }

    public function test_is_returns_false_when_no_values_are_given(): void
    {
        $environment = $this->createEnvironment('production');

        $this->assertFalse($environment->is());
    }

    private function createEnvironment(string $value): AbstractEnvironment
    {
        return new class($value) extends AbstractEnvironment {
            public function __construct(private string $value) {}
            public function getValue(): string { return $this->value; }
        };
    }
}
