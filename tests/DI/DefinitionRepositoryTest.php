<?php

namespace Georgeff\Kernel\Test\DI;

use Georgeff\Kernel\DI\DefinitionInterface;
use Georgeff\Kernel\DI\DefinitionRepository;
use PHPUnit\Framework\TestCase;

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
}
