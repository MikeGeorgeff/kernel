<?php

namespace Georgeff\Kernel\Test\DI;

use Georgeff\Kernel\DI\DefinitionInterface;
use Georgeff\Kernel\DI\DefinitionRepository;
use Georgeff\Kernel\Exception\DefinitionException;
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

    public function test_add_throws_when_redefining_an_existing_id(): void
    {
        $repository = new DefinitionRepository();
        $repository->add('foo', fn() => 'first');

        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('Cannot redefine an existing definition ID: [foo]');

        $repository->add('foo', fn() => 'second');
    }

    public function test_get_returns_null_for_unknown_id(): void
    {
        $repository = new DefinitionRepository();

        $this->assertNull($repository->get('foo'));
    }

    // -------------------------------------------------------------------------
    // isDefined()
    // -------------------------------------------------------------------------

    public function test_is_defined_returns_false_for_an_unknown_id(): void
    {
        $repository = new DefinitionRepository();

        $this->assertFalse($repository->isDefined('foo'));
    }

    public function test_is_defined_returns_true_for_an_added_id(): void
    {
        $repository = new DefinitionRepository();
        $repository->add('foo', fn() => 'bar');

        $this->assertTrue($repository->isDefined('foo'));
    }

    public function test_is_defined_returns_false_for_an_id_that_only_has_a_pending_fallback(): void
    {
        $repository = new DefinitionRepository();
        $repository->addFallback('foo', fn() => 'bar');

        $this->assertFalse($repository->isDefined('foo'));
    }

    // -------------------------------------------------------------------------
    // addFallback() / addFallbacksToDefinitions()
    // -------------------------------------------------------------------------

    public function test_add_fallback_returns_a_definition(): void
    {
        $repository = new DefinitionRepository();

        $definition = $repository->addFallback('foo', fn() => 'bar');

        $this->assertInstanceOf(DefinitionInterface::class, $definition);
    }

    public function test_add_fallback_does_not_add_to_definitions_by_itself(): void
    {
        $repository = new DefinitionRepository();
        $repository->addFallback('foo', fn() => 'bar');

        $this->assertNull($repository->get('foo'));
    }

    public function test_add_fallbacks_to_definitions_adds_a_fallback_for_an_undefined_id(): void
    {
        $repository = new DefinitionRepository();
        $factory    = fn() => 'fallback';
        $repository->addFallback('foo', $factory);

        $repository->addFallbacksToDefinitions();

        $this->assertSame($factory, $repository->get('foo')?->getFactory());
    }

    public function test_add_fallbacks_to_definitions_does_not_override_an_existing_definition(): void
    {
        $repository = new DefinitionRepository();
        $factory    = fn() => 'real';
        $repository->add('foo', $factory);
        $repository->addFallback('foo', fn() => 'fallback');

        $repository->addFallbacksToDefinitions();

        $this->assertSame($factory, $repository->get('foo')?->getFactory());
    }

    public function test_add_fallback_called_twice_for_the_same_id_lets_the_last_one_win(): void
    {
        $repository = new DefinitionRepository();
        $repository->addFallback('foo', fn() => 'first');
        $factory = fn() => 'second';
        $repository->addFallback('foo', $factory);

        $repository->addFallbacksToDefinitions();

        $this->assertSame($factory, $repository->get('foo')?->getFactory());
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

    public function test_get_instropection_data_returns_empty_array_when_no_definitions(): void
    {
        $repository = new DefinitionRepository();

        $this->assertSame([], $repository->getInstropectionData());
    }

    public function test_get_instropection_data_returns_introspection_data(): void
    {
        $repository = new DefinitionRepository();

        $repository->add('foo', fn() => 'bar')->share()->alias('baz')->tag('sampled');

        $data = $repository->getInstropectionData();

        $this->assertArrayHasKey('foo', $data);
        $this->assertTrue($data['foo']['shared']);
        $this->assertSame(['baz'], $data['foo']['aliases']);
        $this->assertSame(['sampled'], $data['foo']['tags']);
    }

    public function test_get_instropection_data_reflects_definition_state_at_call_time(): void
    {
        $repository = new DefinitionRepository();
        $definition = $repository->add('foo', fn() => 'bar');

        $definition->share()->alias('baz');

        $raw = $repository->getInstropectionData();

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

        $this->expectException(DefinitionException::class);
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

        $this->expectException(DefinitionException::class);
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

    // -------------------------------------------------------------------------
    // gc()
    // -------------------------------------------------------------------------

    public function test_gc_is_a_noop_on_an_empty_repository(): void
    {
        $repository = new DefinitionRepository();
        $repository->gc();

        $this->assertSame([], $repository->all());
    }

    public function test_gc_clears_all_definitions(): void
    {
        $repository = new DefinitionRepository();
        $repository->add('foo', fn() => 'bar');
        $repository->gc();

        $this->assertSame([], $repository->all());
    }

    public function test_gc_causes_get_to_return_null_for_a_previously_added_definition(): void
    {
        $repository = new DefinitionRepository();
        $repository->add('foo', fn() => 'bar');
        $repository->gc();

        $this->assertNull($repository->get('foo'));
    }

    public function test_gc_clears_pending_decorators(): void
    {
        $repository = new DefinitionRepository();
        $repository->add('foo', fn() => 'original');
        $repository->decorate('foo', fn($inner, $c) => $inner);
        $repository->gc();

        // Definition is gone too, so this would throw if the decorator survived gc().
        $repository->applyDecorators();

        $this->assertSame([], $repository->all());
    }

    public function test_gc_clears_pending_overrides(): void
    {
        $repository = new DefinitionRepository();
        $repository->add('foo', fn() => 'original');
        $repository->override('foo', fn() => 'overridden');
        $repository->gc();

        // Definition is gone too, so this would throw if the override survived gc().
        $repository->applyOverrides();

        $this->assertSame([], $repository->all());
    }

    public function test_gc_clears_pending_fallbacks(): void
    {
        $repository = new DefinitionRepository();
        $repository->addFallback('foo', fn() => 'fallback');
        $repository->gc();

        $repository->addFallbacksToDefinitions();

        $this->assertSame([], $repository->all());
    }

    public function test_repository_is_reusable_after_gc(): void
    {
        $repository = new DefinitionRepository();
        $repository->add('foo', fn() => 'original');
        $repository->gc();

        $repository->add('bar', fn() => 'baz');

        $this->assertSame(['bar' => $repository->get('bar')], $repository->all());
    }
}
