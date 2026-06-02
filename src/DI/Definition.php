<?php

namespace Georgeff\Kernel\DI;

use Psr\Container\ContainerInterface;

final class Definition implements DefinitionInterface
{
    /**
     * @var callable(ContainerInterface): mixed
     */
    private $factory;

    private bool $shared = false;

    /**
     * @var string[]
     */
    private array $aliases = [];

    /**
     * @var string[]
     */
    private array $tags = [];

    /**
     * @param callable(ContainerInterface): mixed $factory
     */
    public function __construct(private readonly string $id, callable $factory)
    {
        $this->factory = $factory;
    }

    public static function for(string $id, callable $factory): static
    {
        return new static($id, $factory);
    }

    public function share(): static
    {
        $this->shared = true;

        return $this;
    }

    public function alias(string $alias): static
    {
        $this->aliases[] = $alias;

        return $this;
    }

    public function tag(string $tag): static
    {
        $this->tags[] = $tag;

        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getFactory(): callable
    {
        return $this->factory;
    }

    public function isShared(): bool
    {
        return $this->shared;
    }

    public function getAliases(): array
    {
        return $this->aliases;
    }

    public function getTags(): array
    {
        return $this->tags;
    }
}
