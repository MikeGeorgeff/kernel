<?php

namespace Georgeff\Kernel\DI;

interface TagRegistryInterface
{
    /**
     * Resolve and retrieve all services for a tag
     *
     * @return mixed[]
     */
    public function getTagged(string $tag): array;

    /**
     * Retrieve all service IDs for a tag
     *
     * @return string[]
     */
    public function getTaggedIds(string $tag): array;
}
