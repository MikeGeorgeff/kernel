<?php

namespace Georgeff\Kernel\Test\DI;

use Georgeff\Kernel\Contract\ContainerBuilderInterface;
use Georgeff\Kernel\DI\ContainerBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class ContainerBuilderTest extends TestCase
{
    public function test_it_implements_container_builder_interface(): void
    {
        $this->assertInstanceOf(ContainerBuilderInterface::class, new ContainerBuilder());
    }

    public function test_get_container_returns_psr_container(): void
    {
        $builder = new ContainerBuilder();

        $this->assertInstanceOf(ContainerInterface::class, $builder->getContainer());
    }

    public function test_registered_definition_is_resolvable(): void
    {
        $builder = new ContainerBuilder();
        $builder->register('foo', fn() => 'bar');

        $this->assertSame('bar', $builder->getContainer()->get('foo'));
    }

    public function test_shared_definition_returns_same_instance(): void
    {
        $builder = new ContainerBuilder();
        $builder->register('foo', fn() => new \stdClass(), true);

        $container = $builder->getContainer();

        $this->assertSame($container->get('foo'), $container->get('foo'));
    }

    public function test_non_shared_definition_returns_new_instance_each_call(): void
    {
        $builder = new ContainerBuilder();
        $builder->register('foo', fn() => new \stdClass(), false);

        $container = $builder->getContainer();

        $this->assertNotSame($container->get('foo'), $container->get('foo'));
    }

    public function test_alias_resolves_to_original_definition(): void
    {
        $builder = new ContainerBuilder();
        $builder->register('foo', fn() => 'bar', false, ['foo.alias']);

        $container = $builder->getContainer();

        $this->assertSame('bar', $container->get('foo.alias'));
    }

    public function test_multiple_aliases_all_resolve(): void
    {
        $builder = new ContainerBuilder();
        $builder->register('foo', fn() => 'bar', false, ['alias.one', 'alias.two']);

        $container = $builder->getContainer();

        $this->assertSame('bar', $container->get('alias.one'));
        $this->assertSame('bar', $container->get('alias.two'));
    }

    public function test_on_resolved_callback_fires_after_factory_runs(): void
    {
        $builder = new ContainerBuilder();
        $builder->register('foo', fn() => new \stdClass());

        $calls = [];
        $builder->onResolved(function (string $id, mixed $instance) use (&$calls) {
            $calls[] = [$id, $instance];
        });

        $instance = $builder->getContainer()->get('foo');

        $this->assertCount(1, $calls);
        $this->assertSame('foo', $calls[0][0]);
        $this->assertSame($instance, $calls[0][1]);
    }

    public function test_on_resolved_does_not_fire_on_cache_hit(): void
    {
        $builder = new ContainerBuilder();
        $builder->register('foo', fn() => new \stdClass(), true);
        $builder->getContainer()->get('foo');

        $count = 0;
        $builder->onResolved(function () use (&$count) {
            $count++;
        });

        $builder->getContainer()->get('foo');

        $this->assertSame(0, $count);
    }

    public function test_on_resolved_receives_canonical_id_not_alias(): void
    {
        $builder = new ContainerBuilder();
        $builder->register('foo', fn() => new \stdClass(), false, ['foo.alias']);

        $receivedId = null;
        $builder->onResolved(function (string $id) use (&$receivedId) {
            $receivedId = $id;
        });

        $builder->getContainer()->get('foo.alias');

        $this->assertSame('foo', $receivedId);
    }

    public function test_multiple_on_resolved_callbacks_all_fire(): void
    {
        $builder = new ContainerBuilder();
        $builder->register('foo', fn() => new \stdClass());

        $fired = [];
        $builder->onResolved(function () use (&$fired) { $fired[] = 'a'; });
        $builder->onResolved(function () use (&$fired) { $fired[] = 'b'; });

        $builder->getContainer()->get('foo');

        $this->assertSame(['a', 'b'], $fired);
    }

    public function test_on_resolving_callback_fires_before_factory_runs(): void
    {
        $builder = new ContainerBuilder();
        $builder->register('foo', fn() => new \stdClass());

        $calls = [];
        $builder->onResolving(function (string $id) use (&$calls) {
            $calls[] = $id;
        });

        $builder->getContainer()->get('foo');

        $this->assertSame(['foo'], $calls);
    }
}
