<?php

namespace Georgeff\Kernel\Profiler;

use Georgeff\Kernel\Contract\DebuggableInterface;

/**
 * @internal
 */
final class Phase implements DebuggableInterface
{
    private ?float $start = null;

    private ?float $end = null;

    private ?int $memStart = null;

    private ?int $memEnd = null;

    public function __construct(private readonly string $name) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function start(): float
    {
        $this->memStart = memory_get_usage(false);

        return $this->start = microtime(true);
    }

    public function stop(): float
    {
        $this->memEnd = memory_get_usage(false);

        return $this->end = microtime(true);
    }

    public function getStartTime(): ?float
    {
        return $this->start;
    }

    public function getEndTime(): ?float
    {
        return $this->end;
    }

    public function getStartMemory(): ?int
    {
        return $this->memStart;
    }

    public function getEndMemory(): ?int
    {
        return $this->memEnd;
    }

    public function getDuration(): ?float
    {
        return $this->isIncomplete()
            ? null
            : $this->end - $this->start;
    }

    public function getMemoryUsage(): ?int
    {
        return $this->isIncomplete()
            ? null
            : $this->memEnd - $this->memStart;
    }

    public function isIncomplete(): bool
    {
        return null === $this->start || null === $this->end;
    }

    public function getDebugInfo(): array
    {
        return [
            'start.time'   => $this->getStartTime(),
            'end.time'     => $this->getEndTime(),
            'duration'     => $this->getDuration(),
            'memory.usage' => $this->getMemoryUsage(),
        ];
    }
}
