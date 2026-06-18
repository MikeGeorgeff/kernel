<?php

namespace Georgeff\Kernel\DI;

use Georgeff\Kernel\KernelException;
use Psr\Container\ContainerInterface;

/**
 * @internal
 */
final class DefinitionRepository
{
    /**
     * @var array<string, DefinitionInterface>
     */
    private array $definitions = [];

    /**
     * @var array<string, list<array{factory: callable(ContainerInterface): mixed, innerId: string}>>
     */
    private array $decorators = [];

    public function add(string $id, callable $factory): DefinitionInterface
    {
        return $this->definitions[$id] = Definition::for($id, $factory);
    }

    public function get(string $id): ?DefinitionInterface
    {
        return $this->definitions[$id] ?? null;
    }

    /**
     * @param callable(mixed, ContainerInterface): mixed $decorator
     */
    public function decorate(string $id, callable $decorator): void
    {
        $index = $this->getInnerDecoratorIndex($id);

        $innerServiceId = "_{$id}.inner.{$index}";

        $factory = function (ContainerInterface $c) use ($innerServiceId, $decorator): mixed {
            return $decorator($c->get($innerServiceId), $c);
        };

        $this->decorators[$id][] = ['factory' => $factory, 'innerId' => $innerServiceId];
    }

    private function getInnerDecoratorIndex(string $id): int
    {
        return count($this->decorators[$id] ?? []);
    }

    private function applyDecoratorsForDefinition(string $id): void
    {
        if (null === ($inner = $this->get($id))) {
            throw new KernelException("Cannot decorate a non-existing definition ID: [{$id}]");
        }

        $decorators   = $this->decorators[$id];
        $outerFactory = $inner->getFactory();

        foreach ($decorators as $i => $decorator) {
            $innerFactory = 0 === $i ? $inner->getFactory() : $decorators[$i - 1]['factory'];

            $this->add($decorator['innerId'], $innerFactory);

            $outerFactory = $decorator['factory'];
        }

        $decorated = $this->add($id, $outerFactory);

        if ($inner->isShared()) {
            $decorated->share();
        }

        foreach ($inner->getAliases() as $alias) {
            $decorated->alias($alias);
        }

        foreach ($inner->getTags() as $tag) {
            $decorated->tag($tag);
        }
    }

    /**
     * @return array<string, DefinitionInterface>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    public function applyDecorators(): void
    {
        foreach (array_keys($this->decorators) as $id) {
            $this->applyDecoratorsForDefinition($id);
        }
    }

    /**
     * @return array<string, array{factory: callable, shared: bool, aliases: string[]}>
     */
    public function getRaw(): array
    {
        $raw = [];

        foreach ($this->all() as $definition) {
            $raw[$definition->getId()] = [
                'factory' => $definition->getFactory(),
                'shared'  => $definition->isShared(),
                'aliases' => $definition->getAliases(),
            ];
        }

        return $raw;
    }
}
