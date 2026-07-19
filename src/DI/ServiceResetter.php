<?php

namespace Georgeff\Kernel\DI;

use Georgeff\Kernel\Contract\ResettableInterface;

/**
 * @internal
 */
final class ServiceResetter
{
    /**
     * @var array<string, ResettableInterface>
     */
    private array $services = [];

    public function add(string $id, ResettableInterface $service): void
    {
        $this->services[$id] = $service;
    }

    public function reset(): void
    {
        foreach ($this->services as $service) {
            $service->reset();
        }
    }
}
