<?php

namespace Georgeff\Kernel\Debug;

final class ServiceResolutionProfile implements DebuggableInterface
{
    /**
     * @var array<string, ResolvedService>
     */
    private array $resolved = [];

    /**
     * @var array<string, true>
     */
    private array $unresolved = [];

    /**
     * @var array<string, string>
     */
    private array $aliases = [];

    /**
     * @param array<string, array{factory: callable, shared: bool, aliases: string[]}> $definitions
     */
    public function __construct(array $definitions)
    {
        foreach ($definitions as $id => $definition) {
            $this->unresolved[$id] = true;

            foreach ($definition['aliases'] as $alias) {
                $this->aliases[$alias] = $id;
            }
        }
    }

    public function resolve(string $id, float $resolutionTime): ResolvedService
    {
        $id = $this->getId($id);

        if (!isset($this->resolved[$id])) {
            $this->resolved[$id] = $resolved = new ResolvedService($id);

            unset($this->unresolved[$id]);
        } else {
            $resolved = $this->resolved[$id];
        }

        return $resolved->incrementResolutionCount()
                        ->addResolutionTime($resolutionTime);
    }

    private function getId(string $id): string
    {
        return $this->aliases[$id] ?? $id;
    }

    /**
     * @return array<string, array{'resolutionCount': int, 'totalResolutionTime': float}>
     */
    public function getResolvedServices(): array
    {
        $services = [];

        foreach ($this->resolved as $item) {
            $services = array_merge($services, $item->getDebugInfo());
        }

        return $services;
    }

    /**
     * @return string[]
     */
    public function getUnresolvedServices(): array
    {
        return array_keys($this->unresolved);
    }

    /**
     * @return array{'resolved': array<string, array{'resolutionCount': int, 'totalResolutionTime': float}>, 'unresolved': string[]}
     */
    public function getDebugInfo(): array
    {
        return [
            'resolved'   => $this->getResolvedServices(),
            'unresolved' => $this->getUnresolvedServices(),
        ];
    }
}
