<?php

namespace Georgeff\Kernel\Debug;

/**
 * @internal
 */
final class ServiceResolution implements DebuggableInterface
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

    public function resolve(string $id, mixed $resolved): ResolvedService
    {
        $id = $this->getId($id);

        if (!isset($this->resolved[$id])) {
            $this->resolved[$id] = $service = new ResolvedService($id, $resolved);

            unset($this->unresolved[$id]);
        } else {
            $service = $this->resolved[$id];
        }

        return $service->incrementResolutionCount();
    }

    private function getId(string $id): string
    {
        return $this->aliases[$id] ?? $id;
    }

    /**
     * @return array{resolved: array<string, array{resolutionCount: int, debugInfo?: array<mixed>}>, unresolved: string[]}
     */
    public function getDebugInfo(): array
    {
        $info = ['resolved' => [], 'unresolved' => []];

        foreach ($this->resolved as $id => $service) {
            $info['resolved'][$id] = $service->getDebugInfo();
        }

        foreach ($this->unresolved as $id => $value) {
            $info['unresolved'][] = $id;
        }

        return $info;
    }
}
