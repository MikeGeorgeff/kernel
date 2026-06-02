<?php

namespace Georgeff\Kernel\DI;

use Psr\Container\ContainerInterface;

interface DefinitionInterface
{
    /**
     * @param callable(ContainerInterface): mixed $factory
     */
    public static function for(string $id, callable $factory): static;

    /**
     * Set a definition as shared
     */
    public function share(): static;

    /**
     * Add a definition alias
     */
    public function alias(string $alias): static;

    /**
     * Tag a definition
     */
    public function tag(string $tag): static;

    public function getId(): string;

    /**
     * @return callable(ContainerInterface): mixed
     */
    public function getFactory(): callable;

    public function isShared(): bool;

    /**
     * @return string[]
     */
    public function getAliases(): array;

    /**
     * @return string[]
     */
    public function getTags(): array;
}
