<?php

namespace Georgeff\Kernel\Test;

use Georgeff\Kernel\DefaultServiceRegistrar;
use Georgeff\Kernel\ResolvingAwareServiceRegistrar;
use Georgeff\Kernel\ServiceRegistrar;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class DefaultServiceRegistrarTest extends TestCase
{
    public function test_it_implements_service_registrar(): void
    {
        $this->assertInstanceOf(ServiceRegistrar::class, new DefaultServiceRegistrar());
    }

    public function test_it_implements_resolving_aware_service_registrar(): void
    {
        $this->assertInstanceOf(ResolvingAwareServiceRegistrar::class, new DefaultServiceRegistrar());
    }

    public function test_get_container_returns_psr_container(): void
    {
        $registrar = new DefaultServiceRegistrar();

        $this->assertInstanceOf(ContainerInterface::class, $registrar->getContainer());
    }

    public function test_registered_definition_is_resolvable(): void
    {
        $registrar = new DefaultServiceRegistrar();
        $registrar->register('foo', fn() => 'bar');

        $this->assertSame('bar', $registrar->getContainer()->get('foo'));
    }

    public function test_shared_definition_returns_same_instance(): void
    {
        $registrar = new DefaultServiceRegistrar();
        $registrar->register('foo', fn() => new \stdClass(), true);

        $container = $registrar->getContainer();

        $this->assertSame($container->get('foo'), $container->get('foo'));
    }

    public function test_non_shared_definition_returns_new_instance_each_call(): void
    {
        $registrar = new DefaultServiceRegistrar();
        $registrar->register('foo', fn() => new \stdClass(), false);

        $container = $registrar->getContainer();

        $this->assertNotSame($container->get('foo'), $container->get('foo'));
    }

    public function test_alias_resolves_to_original_definition(): void
    {
        $registrar = new DefaultServiceRegistrar();
        $registrar->register('foo', fn() => 'bar', false, ['foo.alias']);

        $container = $registrar->getContainer();

        $this->assertSame('bar', $container->get('foo.alias'));
    }

    public function test_multiple_aliases_all_resolve(): void
    {
        $registrar = new DefaultServiceRegistrar();
        $registrar->register('foo', fn() => 'bar', false, ['alias.one', 'alias.two']);

        $container = $registrar->getContainer();

        $this->assertSame('bar', $container->get('alias.one'));
        $this->assertSame('bar', $container->get('alias.two'));
    }

    public function test_after_resolved_callback_fires_after_factory_runs(): void
    {
        $registrar = new DefaultServiceRegistrar();
        $registrar->register('foo', fn() => new \stdClass());

        $calls = [];
        $registrar->afterResolved(function (string $id, mixed $instance) use (&$calls) {
            $calls[] = [$id, $instance];
        });

        $instance = $registrar->getContainer()->get('foo');

        $this->assertCount(1, $calls);
        $this->assertSame('foo', $calls[0][0]);
        $this->assertSame($instance, $calls[0][1]);
    }

    public function test_after_resolved_does_not_fire_on_cache_hit(): void
    {
        $registrar = new DefaultServiceRegistrar();
        $registrar->register('foo', fn() => new \stdClass(), true);
        $registrar->getContainer()->get('foo');

        $count = 0;
        $registrar->afterResolved(function () use (&$count) {
            $count++;
        });

        $registrar->getContainer()->get('foo');

        $this->assertSame(0, $count);
    }

    public function test_after_resolved_receives_canonical_id_not_alias(): void
    {
        $registrar = new DefaultServiceRegistrar();
        $registrar->register('foo', fn() => new \stdClass(), false, ['foo.alias']);

        $receivedId = null;
        $registrar->afterResolved(function (string $id) use (&$receivedId) {
            $receivedId = $id;
        });

        $registrar->getContainer()->get('foo.alias');

        $this->assertSame('foo', $receivedId);
    }

    public function test_multiple_after_resolved_callbacks_all_fire(): void
    {
        $registrar = new DefaultServiceRegistrar();
        $registrar->register('foo', fn() => new \stdClass());

        $fired = [];
        $registrar->afterResolved(function () use (&$fired) { $fired[] = 'a'; });
        $registrar->afterResolved(function () use (&$fired) { $fired[] = 'b'; });

        $registrar->getContainer()->get('foo');

        $this->assertSame(['a', 'b'], $fired);
    }
}
