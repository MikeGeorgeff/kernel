<?php

namespace Georgeff\Kernel\Debug;

use Psr\Container\ContainerInterface;

final class DebugContainer implements ContainerInterface, DebuggableInterface
{
    private ContainerInterface $container;

    private ServiceResolutionProfile $profile;

    /**
     * @var array<string, DebuggableInterface>
     */
    private array $debuggable = [];

    /**
     * @param array<string, array{factory: callable, shared: bool, aliases: string[]}> $definitions
     */
    public function __construct(ContainerInterface $container, array $definitions)
    {
        $this->container = $container;
        $this->profile   = new ServiceResolutionProfile($definitions);
    }

    public function has(string $id): bool
    {
        return $this->container->has($id);
    }

    public function get(string $id)
    {
        $start = microtime(true);

        $resolved = $this->container->get($id);

        $end = microtime(true);

        $this->profile->resolve($id, $end - $start);

        if ($resolved instanceof DebuggableInterface) {
            $this->debuggable[$id] = $resolved;
        }

        return $resolved;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDebugInfo(): array
    {
        $services = [];

        foreach ($this->debuggable as $id => $debuggable) {
            $services[$id] = $debuggable->getDebugInfo();
        }

        return [
            'serviceResolutionProfile' => $this->profile->getDebugInfo(),
            'servicesDebugInfo'        => $services,
        ];
    }
}
