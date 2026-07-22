<?php

namespace Georgeff\Kernel\Profiler;

use Georgeff\Kernel\Contract\DebuggableInterface;

/**
 * @internal
 */
final class Profile implements DebuggableInterface
{
    private readonly Phase $timer;

    /**
     * Phase timer
     *
     * @var array<string, Phase>
     */
    private array $phases = [];

    public function __construct(private readonly string $name)
    {
        $this->timer = new Phase('__profile__');
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isIncomplete(): bool
    {
        return $this->timer->isIncomplete();
    }

    public function start(): float
    {
        return $this->timer->start();
    }

    public function stop(): float
    {
        return $this->timer->stop();
    }

    public function getStartTime(): ?float
    {
        return $this->timer->getStartTime();
    }

    public function getEndTime(): ?float
    {
        return $this->timer->getEndTime();
    }

    public function hasPhase(string $phase): bool
    {
        return isset($this->phases[$phase]);
    }

    public function startPhase(string $phase): float
    {
        $this->phases[$phase] = new Phase($phase);

        return $this->phases[$phase]->start();
    }

    public function stopPhase(string $phase): float
    {
        if (!$this->hasPhase($phase)) {
            $this->phases[$phase] = new Phase($phase);
        }

        return $this->phases[$phase]->stop();
    }

    public function getPhaseDuration(string $phase): ?float
    {
        return $this->hasPhase($phase)
            ? $this->phases[$phase]->getDuration()
            : null;
    }

    public function getOverallDuration(): ?float
    {
        return $this->timer->getDuration();
    }

    public function getDebugInfo(): array
    {
        $info = [
            'start.time'   => $this->getStartTime(),
            'end.time'     => $this->getEndTime(),
            'duration'     => $this->getOverallDuration(),
            'memory.usage' => $this->timer->getMemoryUsage(),
        ];

        foreach ($this->phases as $phase) {
            $info['phases'][$phase->getName()] = $phase->getDebugInfo();
        }

        return $info;
    }
}
