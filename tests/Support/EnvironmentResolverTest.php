<?php

namespace Georgeff\Kernel\Test\Support;

use Georgeff\Kernel\Contract\EnvironmentInterface;
use Georgeff\Kernel\Environment\Development;
use Georgeff\Kernel\Environment\Local;
use Georgeff\Kernel\Environment\Production;
use Georgeff\Kernel\Environment\Staging;
use Georgeff\Kernel\Environment\Testing;
use Georgeff\Kernel\Support\EnvironmentResolver;
use PHPUnit\Framework\TestCase;

class EnvironmentResolverTest extends TestCase
{
    // -------------------------------------------------------------------------
    // resolve() — default registry
    // -------------------------------------------------------------------------

    public function test_resolve_returns_production(): void
    {
        $this->assertInstanceOf(Production::class, new EnvironmentResolver()->resolve('production'));
    }

    public function test_resolve_returns_staging(): void
    {
        $this->assertInstanceOf(Staging::class, new EnvironmentResolver()->resolve('staging'));
    }

    public function test_resolve_returns_development(): void
    {
        $this->assertInstanceOf(Development::class, new EnvironmentResolver()->resolve('development'));
    }

    public function test_resolve_returns_testing(): void
    {
        $this->assertInstanceOf(Testing::class, new EnvironmentResolver()->resolve('testing'));
    }

    public function test_resolve_returns_local(): void
    {
        $this->assertInstanceOf(Local::class, new EnvironmentResolver()->resolve('local'));
    }

    public function test_resolve_throws_for_unknown_name(): void
    {
        $resolver = new EnvironmentResolver();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Environment [bogus] is not a registered enviornment');

        $resolver->resolve('bogus');
    }

    // -------------------------------------------------------------------------
    // register()
    // -------------------------------------------------------------------------

    public function test_register_returns_self(): void
    {
        $resolver = new EnvironmentResolver();

        $result = $resolver->register('custom', CustomEnvironment::class);

        $this->assertSame($resolver, $result);
    }

    public function test_register_adds_a_resolvable_custom_environment(): void
    {
        $resolver = new EnvironmentResolver();
        $resolver->register('custom', CustomEnvironment::class);

        $this->assertInstanceOf(CustomEnvironment::class, $resolver->resolve('custom'));
    }

    public function test_register_overrides_an_existing_name(): void
    {
        $resolver = new EnvironmentResolver();
        $resolver->register('production', CustomEnvironment::class);

        $this->assertInstanceOf(CustomEnvironment::class, $resolver->resolve('production'));
    }

    public function test_register_throws_when_class_does_not_exist(): void
    {
        $resolver = new EnvironmentResolver();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Environment class [Georgeff\Kernel\Test\Support\NonExistentClass] was not found');

        $resolver->register('custom', NonExistentClass::class);
    }

    public function test_register_throws_when_class_does_not_implement_environment_interface(): void
    {
        $resolver = new EnvironmentResolver();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf(
            'Environment class [%s] must be an instance of %s',
            \stdClass::class,
            EnvironmentInterface::class,
        ));

        $resolver->register('custom', \stdClass::class);
    }

    // -------------------------------------------------------------------------
    // registered()
    // -------------------------------------------------------------------------

    public function test_registered_returns_the_default_registry(): void
    {
        $registered = new EnvironmentResolver()->registered();

        $this->assertSame([
            'production'  => Production::class,
            'staging'     => Staging::class,
            'development' => Development::class,
            'testing'     => Testing::class,
            'local'       => Local::class,
        ], $registered);
    }

    public function test_registered_reflects_a_registered_custom_environment(): void
    {
        $resolver = new EnvironmentResolver();
        $resolver->register('custom', CustomEnvironment::class);

        $this->assertSame(CustomEnvironment::class, $resolver->registered()['custom']);
    }
}

final class CustomEnvironment implements EnvironmentInterface
{
    public function getValue(): string
    {
        return 'custom';
    }

    public function is(string ...$values): bool
    {
        return in_array($this->getValue(), $values, true);
    }
}
