<?php

namespace Georgeff\Kernel\Debug;

/**
 * @internal
 */
final class ResolvedService implements DebuggableInterface
{
    private readonly string $id;

    private readonly mixed $resolved;

    private int $resolutionCount = 0;

    public function __construct(string $id, mixed $resolved)
    {
        $this->id       = $id;
        $this->resolved = $resolved;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getResolutionCount(): int
    {
        return $this->resolutionCount;
    }

    public function incrementResolutionCount(): self
    {
        $this->resolutionCount++;

        return $this;
    }

    /**
     * @return array{resolutionCount: int, debugInfo?: array<mixed>}
     */
    public function getDebugInfo(): array
    {
        $info = ['resolutionCount' => $this->resolutionCount];

        if ($this->resolved instanceof DebuggableInterface) {
            $info['debugInfo'] = $this->resolved->getDebugInfo();
        }

        return $info;
    }
}
