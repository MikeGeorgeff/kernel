<?php

namespace Georgeff\Kernel\Debug;

final class ResolvedService implements DebuggableInterface
{
    private string $id;

    private int $resolutionCount = 0;

    private float $resolutionTime = 0.0;

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getResolutionCount(): int
    {
        return $this->resolutionCount;
    }

    public function getResolutionTime(): float
    {
        return $this->resolutionTime;
    }

    public function incrementResolutionCount(): self
    {
        $this->resolutionCount++;

        return $this;
    }

    public function addResolutionTime(float $duration): self
    {
        $this->resolutionTime += $duration;

        return $this;
    }

    /**
     * @return array<string, array{'resolutionCount': int, 'totalResolutionTime': float}>
     */
    public function getDebugInfo(): array
    {
        return [
            $this->id => [
                'resolutionCount'     => $this->resolutionCount,
                'totalResolutionTime' => $this->resolutionTime,
            ],
        ];
    }
}
