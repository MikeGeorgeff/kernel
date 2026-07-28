<?php

namespace Georgeff\Kernel\DI\Profile;

use Georgeff\Kernel\Exception\ProfilerException;
use Georgeff\Kernel\Contract\DebuggableInterface;

/**
 * @internal
 */
final class ServiceResolutionProfile implements DebuggableInterface
{
    /**
     * @var array<string, ServiceProfile>
     */
    private array $resolved = [];

    /**
     * @var array<string, ServiceProfile>
     */
    private array $resolving = [];
    /**
     * @var array<string, true>
     */
    private array $unresolved = [];

    /**
     * @var array<string, true>
     */
    private array $shared = [];

    /**
     * @var array<string, string>
     */
    private array $aliases = [];

    /**
     * @param array<string, array{shared: bool, aliases: string[]}> $definitions
     */
    public function __construct(array $definitions)
    {
        foreach ($definitions as $id => $data) {
            $this->unresolved[$id] = true;

            if ($data['shared']) {
                $this->shared[$id] = true;
            }

            foreach ($data['aliases'] as $alias) {
                $this->aliases[$alias] = $id;
            }
        }
    }

    public function resolving(string $id): void
    {
        $id = $this->resolveId($id);

        if ($this->isResolved($id)) {
            if ($this->isShared($id)) {
                return;
            }

            $service = $this->resolved[$id];
        } else {
            $service = new ServiceProfile($id);
        }

        $this->resolving[$id] = $service->resolving();
    }

    public function resolved(string $id, mixed $instance): void
    {
        $id = $this->resolveId($id);

        ProfilerException::throwIfNot(
            isset($this->resolving[$id]),
            "Service ID [$id] has not started resolving"
        );

        $service = $this->resolving[$id];

        unset($this->resolving[$id], $this->unresolved[$id]);

        $this->resolved[$id] = $service->resolved($instance);
    }

    private function isResolved(string $id): bool
    {
        return isset($this->resolved[$id]);
    }

    private function isShared(string $id): bool
    {
        return isset($this->shared[$id]);
    }

    private function resolveId(string $id): string
    {
        return $this->aliases[$id] ?? $id;
    }

    public function getDebugInfo(): array
    {
        $output = ['resolved' => [], 'unresolved' => []];

        foreach ($this->resolved as $service) {
            $output['resolved'][$service->id] = $service->getDebugInfo();
        }

        $output['unresolved'] = array_keys($this->unresolved);

        return $output;
    }
}
