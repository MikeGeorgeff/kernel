<?php

namespace Georgeff\Kernel\DI;

use Psr\Container\ContainerInterface;

/**
 * @internal
 */
final class TagRegistry implements TagRegistryInterface
{
    /**
     * @param array<string, string[]> $tags
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly array $tags
    ) {}

    public function getTagged(string $tag): array
    {
        $services = [];

        foreach ($this->tags[$tag] ?? [] as $id) {
            $services[] = $this->container->get($id);
        }

        return $services;
    }
}
