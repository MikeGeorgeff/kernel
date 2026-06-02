<?php

namespace Georgeff\Kernel\Test\DI;

use Georgeff\Kernel\DI\Definition;
use Georgeff\Kernel\DI\DefinitionInterface;
use PHPUnit\Framework\TestCase;

class DefinitionTest extends TestCase
{
    public function test_it_implements_definition_interface(): void
    {
        $definition = Definition::for('foo', fn() => 'bar');

        $this->assertInstanceOf(DefinitionInterface::class, $definition);
    }

    public function test_it_returns_the_id(): void
    {
        $definition = Definition::for('foo', fn() => 'bar');

        $this->assertSame('foo', $definition->getId());
    }

    public function test_it_returns_the_factory(): void
    {
        $factory    = fn() => 'bar';
        $definition = Definition::for('foo', $factory);

        $this->assertSame($factory, $definition->getFactory());
    }

    public function test_it_is_not_shared_by_default(): void
    {
        $definition = Definition::for('foo', fn() => 'bar');

        $this->assertFalse($definition->isShared());
    }

    public function test_share_marks_definition_as_shared(): void
    {
        $definition = Definition::for('foo', fn() => 'bar');

        $definition->share();

        $this->assertTrue($definition->isShared());
    }

    public function test_share_returns_same_instance(): void
    {
        $definition = Definition::for('foo', fn() => 'bar');

        $this->assertSame($definition, $definition->share());
    }

    public function test_it_has_no_aliases_by_default(): void
    {
        $definition = Definition::for('foo', fn() => 'bar');

        $this->assertSame([], $definition->getAliases());
    }

    public function test_alias_adds_an_alias(): void
    {
        $definition = Definition::for('foo', fn() => 'bar');

        $definition->alias('baz');

        $this->assertSame(['baz'], $definition->getAliases());
    }

    public function test_alias_accumulates_multiple_aliases(): void
    {
        $definition = Definition::for('foo', fn() => 'bar');

        $definition->alias('baz')->alias('qux');

        $this->assertSame(['baz', 'qux'], $definition->getAliases());
    }

    public function test_alias_returns_same_instance(): void
    {
        $definition = Definition::for('foo', fn() => 'bar');

        $this->assertSame($definition, $definition->alias('baz'));
    }

    public function test_it_has_no_tags_by_default(): void
    {
        $definition = Definition::for('foo', fn() => 'bar');

        $this->assertSame([], $definition->getTags());
    }

    public function test_tag_adds_a_tag(): void
    {
        $definition = Definition::for('foo', fn() => 'bar');

        $definition->tag('my_tag');

        $this->assertSame(['my_tag'], $definition->getTags());
    }

    public function test_tag_accumulates_multiple_tags(): void
    {
        $definition = Definition::for('foo', fn() => 'bar');

        $definition->tag('tag_a')->tag('tag_b');

        $this->assertSame(['tag_a', 'tag_b'], $definition->getTags());
    }

    public function test_tag_returns_same_instance(): void
    {
        $definition = Definition::for('foo', fn() => 'bar');

        $this->assertSame($definition, $definition->tag('my_tag'));
    }

    public function test_fluent_chain(): void
    {
        $factory    = fn() => 'bar';
        $definition = Definition::for('foo', $factory)
            ->share()
            ->alias('baz')
            ->tag('my_tag');

        $this->assertSame('foo', $definition->getId());
        $this->assertSame($factory, $definition->getFactory());
        $this->assertTrue($definition->isShared());
        $this->assertSame(['baz'], $definition->getAliases());
        $this->assertSame(['my_tag'], $definition->getTags());
    }
}
