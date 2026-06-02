<?php

namespace Georgeff\Kernel\DI;

/**
 * @internal
 */
final class DefinitionRepository
{
    /**
     * @var array<string, DefinitionInterface>
     */
    private array $definitions = [];

    public function add(string $id, callable $factory): DefinitionInterface
    {
        return $this->definitions[$id] = Definition::for($id, $factory);
    }

    public function get(string $id): ?DefinitionInterface
    {
        return $this->definitions[$id] ?? null;
    }

    /**
     * @return array<string, DefinitionInterface>
     */
    public function all(): array
    {
        return $this->definitions;
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
