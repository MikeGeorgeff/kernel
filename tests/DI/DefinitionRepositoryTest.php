<?php

namespace Georgeff\Kernel\Test\DI;

use Georgeff\Kernel\DI\DefinitionInterface;
use Georgeff\Kernel\DI\DefinitionRepository;
use Georgeff\Kernel\KernelException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class DefinitionRepositoryTest extends TestCase
{
    public function test_add_returns_a_definition(): void
    {
        $repository = new DefinitionRepository();

        $definition = $repository->add('foo', fn() => 'bar');

        $this->assertInstanceOf(DefinitionInterface::class, $definition);
    }

    public function test_add_stores_definition_by_id(): void
    {
        $repository = new DefinitionRepository();

        $repository->add('foo', fn() => 'bar');

        $this->assertNotNull($repository->get('foo'));
    }

    public function test_add_overwrites_existing_definition(): void
    {
        $repository = new DefinitionRepository();
        $repository->add('foo', fn() => 'first');

        $factory = fn() => 'second';
        $repository->add('foo', $factory);

        $this->assertSame($factory, $repository->get('foo')?->getFactory());
    }

    public function test_get_returns_null_for_unknown_id(): void
    {
        $repository = new DefinitionRepository();

        $this->assertNull($repository->get('foo'));
    }

    public function test_all_returns_empty_array_when_no_definitions(): void
    {
        $repository = new DefinitionRepository();

        $this->assertSame([], $repository->all());
    }

    public function test_all_returns_all_added_definitions(): void
    {
        $repository = new DefinitionRepository();
        $repository->add('foo', fn() => 'foo');
        $repository->add('bar', fn() => 'bar');

        $all = $repository->all();

        $this->assertCount(2, $all);
        $this->assertArrayHasKey('foo', $all);
        $this->assertArrayHasKey('bar', $all);
    }

    public function test_get_raw_returns_empty_array_when_no_definitions(): void
    {
        $repository = new DefinitionRepository();

        $this->assertSame([], $repository->getRaw());
    }

    public function test_get_raw_returns_raw_definition_data(): void
    {
        $repository = new DefinitionRepository();
        $factory    = fn() => 'bar';

        $repository->add('foo', $factory)->share()->alias('baz');

        $raw = $repository->getRaw();

        $this->assertArrayHasKey('foo', $raw);
        $this->assertSame($factory, $raw['foo']['factory']);
        $this->assertTrue($raw['foo']['shared']);
        $this->assertSame(['baz'], $raw['foo']['aliases']);
    }

    public function test_get_raw_reflects_definition_state_at_call_time(): void
    {
        $repository = new DefinitionRepository();
        $definition = $repository->add('foo', fn() => 'bar');

        $definition->share()->alias('baz');

        $raw = $repository->getRaw();

        $this->assertTrue($raw['foo']['shared']);
        $this->assertSame(['baz'], $raw['foo']['aliases']);
    }

    // -------------------------------------------------------------------------
    // decorate()
    // -------------------------------------------------------------------------

    public function test_decorate_does_not_immediately_modify_definitions(): void
    {
        $factory    = fn() => 'original';
        $repository = new DefinitionRepository();
        $repository->add('foo', $factory);
        $repository->decorate('foo', fn($inner, $c) => $inner);

        $this->assertSame($factory, $repository->get('foo')?->getFactory());
    }

    // -------------------------------------------------------------------------
    // applyDecorators()
    // -------------------------------------------------------------------------

    public function test_apply_decorators_is_a_noop_when_no_decorators_registered(): void
    {
        $repository = new DefinitionRepository();
        $repository->add('foo', fn() => 'bar');
        $repository->applyDecorators();

        $this->assertCount(1, $repository->all());
    }

    public function test_apply_decorators_throws_when_definition_not_found(): void
    {
        $repository = new DefinitionRepository();
        $repository->decorate('foo', fn($inner, $c) => $inner);

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Cannot decorate a non-existing definition ID: [foo]');

        $repository->applyDecorators();
    }

    public function test_apply_decorators_registers_inner_definition(): void
    {
        $repository = new DefinitionRepository();
        $repository->add('foo', fn() => 'bar');
        $repository->decorate('foo', fn($inner, $c) => $inner);
        $repository->applyDecorators();

        $this->assertNotNull($repository->get('_foo.inner.0'));
    }

    public function test_apply_decorators_inner_has_original_factory(): void
    {
        $factory    = fn() => 'original';
        $repository = new DefinitionRepository();
        $repository->add('foo', $factory);
        $repository->decorate('foo', fn($inner, $c) => $inner);
        $repository->applyDecorators();

        $this->assertSame($factory, $repository->get('_foo.inner.0')?->getFactory());
    }

    public function test_apply_decorators_inner_has_no_aliases(): void
    {
        $repository = new DefinitionRepository();
        $repository->add('foo', fn() => 'bar')->alias('foo_alias');
        $repository->decorate('foo', fn($inner, $c) => $inner);
        $repository->applyDecorators();

        $this->assertSame([], $repository->get('_foo.inner.0')?->getAliases());
    }

    public function test_apply_decorators_inner_has_no_tags(): void
    {
        $repository = new DefinitionRepository();
        $repository->add('foo', fn() => 'bar')->tag('my.tag');
        $repository->decorate('foo', fn($inner, $c) => $inner);
        $repository->applyDecorators();

        $this->assertSame([], $repository->get('_foo.inner.0')?->getTags());
    }

    public function test_apply_decorators_outer_inherits_shared_from_original(): void
    {
        $repository = new DefinitionRepository();
        $repository->add('foo', fn() => 'bar')->share();
        $repository->decorate('foo', fn($inner, $c) => $inner);
        $repository->applyDecorators();

        $this->assertTrue($repository->get('foo')?->isShared());
    }

    public function test_apply_decorators_outer_is_not_shared_when_original_is_not(): void
    {
        $repository = new DefinitionRepository();
        $repository->add('foo', fn() => 'bar');
        $repository->decorate('foo', fn($inner, $c) => $inner);
        $repository->applyDecorators();

        $this->assertFalse($repository->get('foo')?->isShared());
    }

    public function test_apply_decorators_outer_inherits_aliases_from_original(): void
    {
        $repository = new DefinitionRepository();
        $repository->add('foo', fn() => 'bar')->alias('foo_alias');
        $repository->decorate('foo', fn($inner, $c) => $inner);
        $repository->applyDecorators();

        $this->assertSame(['foo_alias'], $repository->get('foo')?->getAliases());
    }

    public function test_apply_decorators_outer_inherits_tags_from_original(): void
    {
        $repository = new DefinitionRepository();
        $repository->add('foo', fn() => 'bar')->tag('my.tag');
        $repository->decorate('foo', fn($inner, $c) => $inner);
        $repository->applyDecorators();

        $this->assertSame(['my.tag'], $repository->get('foo')?->getTags());
    }

    public function test_apply_decorators_outer_factory_passes_inner_and_container_to_decorator(): void
    {
        $inner     = new \stdClass();
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->with('_foo.inner.0')->willReturn($inner);

        $received = [];
        $decorator = function ($i, $c) use (&$received) {
            $received = [$i, $c];
            return $i;
        };

        $repository = new DefinitionRepository();
        $repository->add('foo', fn() => $inner);
        $repository->decorate('foo', $decorator);
        $repository->applyDecorators();

        ($repository->get('foo')?->getFactory())($container);

        $this->assertSame($inner, $received[0]);
        $this->assertSame($container, $received[1]);
    }

    public function test_apply_decorators_does_not_affect_undecorated_definitions(): void
    {
        $factory    = fn() => 'bar';
        $repository = new DefinitionRepository();
        $repository->add('foo', fn() => 'foo');
        $repository->add('bar', $factory);
        $repository->decorate('foo', fn($inner, $c) => $inner);
        $repository->applyDecorators();

        $this->assertSame($factory, $repository->get('bar')?->getFactory());
    }

    public function test_apply_decorators_chains_multiple_decorators_innermost_first(): void
    {
        $repository = new DefinitionRepository();
        $repository->add('foo', fn() => 'original');
        $repository->decorate('foo', fn($inner, $c) => $inner . '_A');
        $repository->decorate('foo', fn($inner, $c) => $inner . '_B');
        $repository->applyDecorators();

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturnCallback(
            fn(string $id) => ($repository->get($id)?->getFactory())($container)
        );

        $result = ($repository->get('foo')?->getFactory())($container);

        $this->assertSame('original_A_B', $result);
    }

    // -------------------------------------------------------------------------
    // override()
    // -------------------------------------------------------------------------

    public function test_override_returns_a_definition(): void
    {
        $repository = new DefinitionRepository();
        $repository->add('foo', fn() => 'original');

        $definition = $repository->override('foo', fn() => 'overridden');

        $this->assertInstanceOf(DefinitionInterface::class, $definition);
    }

    public function test_override_does_not_immediately_modify_definitions(): void
    {
        $factory    = fn() => 'original';
        $repository = new DefinitionRepository();
        $repository->add('foo', $factory);
        $repository->override('foo', fn() => 'overridden');

        $this->assertSame($factory, $repository->get('foo')?->getFactory());
    }

    // -------------------------------------------------------------------------
    // applyOverrides()
    // -------------------------------------------------------------------------

    public function test_apply_overrides_is_a_noop_when_no_overrides_registered(): void
    {
        $repository = new DefinitionRepository();
        $repository->add('foo', fn() => 'bar');
        $repository->applyOverrides();

        $this->assertCount(1, $repository->all());
    }

    public function test_apply_overrides_throws_when_definition_not_found(): void
    {
        $repository = new DefinitionRepository();
        $repository->override('foo', fn() => 'overridden');

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Cannot override a non-existing definition');

        $repository->applyOverrides();
    }

    public function test_apply_overrides_replaces_the_definition(): void
    {
        $repository = new DefinitionRepository();
        $repository->add('foo', fn() => 'original');
        $repository->override('foo', fn() => 'overridden');
        $repository->applyOverrides();

        $this->assertSame('overridden', ($repository->get('foo')?->getFactory())());
    }

    public function test_apply_overrides_does_not_inherit_shared_from_original(): void
    {
        $repository = new DefinitionRepository();
        $repository->add('foo', fn() => 'original')->share();
        $repository->override('foo', fn() => 'overridden');
        $repository->applyOverrides();

        $this->assertFalse($repository->get('foo')?->isShared());
    }

    public function test_apply_overrides_honors_shared_when_explicitly_set(): void
    {
        $repository = new DefinitionRepository();
        $repository->add('foo', fn() => 'original');
        $repository->override('foo', fn() => 'overridden')->share();
        $repository->applyOverrides();

        $this->assertTrue($repository->get('foo')?->isShared());
    }

    public function test_apply_overrides_does_not_inherit_aliases_from_original(): void
    {
        $repository = new DefinitionRepository();
        $repository->add('foo', fn() => 'original')->alias('foo_alias');
        $repository->override('foo', fn() => 'overridden');
        $repository->applyOverrides();

        $this->assertSame([], $repository->get('foo')?->getAliases());
    }

    public function test_apply_overrides_does_not_inherit_tags_from_original(): void
    {
        $repository = new DefinitionRepository();
        $repository->add('foo', fn() => 'original')->tag('my.tag');
        $repository->override('foo', fn() => 'overridden');
        $repository->applyOverrides();

        $this->assertSame([], $repository->get('foo')?->getTags());
    }

    public function test_apply_overrides_with_preserve_inherits_shared_from_original(): void
    {
        $repository = new DefinitionRepository();
        $repository->add('foo', fn() => 'original')->share();
        $repository->override('foo', fn() => 'overridden', preserve: true);
        $repository->applyOverrides();

        $this->assertTrue($repository->get('foo')?->isShared());
    }

    public function test_apply_overrides_with_preserve_does_not_share_when_original_is_not(): void
    {
        $repository = new DefinitionRepository();
        $repository->add('foo', fn() => 'original');
        $repository->override('foo', fn() => 'overridden', preserve: true);
        $repository->applyOverrides();

        $this->assertFalse($repository->get('foo')?->isShared());
    }

    public function test_apply_overrides_with_preserve_inherits_aliases_from_original(): void
    {
        $repository = new DefinitionRepository();
        $repository->add('foo', fn() => 'original')->alias('foo_alias');
        $repository->override('foo', fn() => 'overridden', preserve: true);
        $repository->applyOverrides();

        $this->assertSame(['foo_alias'], $repository->get('foo')?->getAliases());
    }

    public function test_apply_overrides_with_preserve_inherits_tags_from_original(): void
    {
        $repository = new DefinitionRepository();
        $repository->add('foo', fn() => 'original')->tag('my.tag');
        $repository->override('foo', fn() => 'overridden', preserve: true);
        $repository->applyOverrides();

        $this->assertSame(['my.tag'], $repository->get('foo')?->getTags());
    }

    public function test_apply_overrides_with_preserve_still_uses_the_override_factory(): void
    {
        $repository = new DefinitionRepository();
        $repository->add('foo', fn() => 'original');
        $repository->override('foo', fn() => 'overridden', preserve: true);
        $repository->applyOverrides();

        $this->assertSame('overridden', ($repository->get('foo')?->getFactory())());
    }

    public function test_apply_overrides_does_not_affect_other_definitions(): void
    {
        $factory    = fn() => 'bar';
        $repository = new DefinitionRepository();
        $repository->add('foo', fn() => 'foo');
        $repository->add('bar', $factory);
        $repository->override('foo', fn() => 'overridden');
        $repository->applyOverrides();

        $this->assertSame($factory, $repository->get('bar')?->getFactory());
    }

    public function test_apply_overrides_last_override_wins(): void
    {
        $repository = new DefinitionRepository();
        $repository->add('foo', fn() => 'original');
        $repository->override('foo', fn() => 'first override');
        $repository->override('foo', fn() => 'second override');
        $repository->applyOverrides();

        $this->assertSame('second override', ($repository->get('foo')?->getFactory())());
    }
}
