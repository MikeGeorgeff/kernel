<?php

namespace Georgeff\Kernel\Debug;

final class Profiler implements DebuggableInterface
{
    /**
     * Global timer
     */
    private ?float $start = null;
    private ?float $end = null;

    /**
     * Phase timer
     *
     * @var array<string, array{'start.time'?: float, 'end.time'?: float}>
     */
    private array $phases = [];

    /**
     * Start the global timer
     */
    public function start(): float
    {
        return $this->start = microtime(true);
    }

    /**
     * Stop the global timer
     */
    public function stop(): float
    {
        return $this->end = microtime(true);
    }

    /**
     * Start profiling a phase
     */
    public function startPhase(string $phase): float
    {
        return $this->phases[$phase]['start.time'] = microtime(true);
    }

    /**
     * Stop profiling a phase
     */
    public function stopPhase(string $phase): float
    {
        return $this->phases[$phase]['end.time'] = microtime(true);
    }

    /**
     * Get phase time
     */
    public function getPhaseDuration(string $phase): float
    {
        if (!isset($this->phases[$phase])
            || !isset($this->phases[$phase]['start.time'], $this->phases[$phase]['end.time'])
        ) {
            return -INF;
        }

        return $this->phases[$phase]['end.time'] - $this->phases[$phase]['start.time'];
    }

    /**
     * Get the overall duration clocked by the global timer
     */
    public function getOverallDuration(): float
    {
        if (!$this->start || !$this->end) {
            return -INF;
        }

        return $this->end - $this->start;
    }

    /**
     * @inheritdoc
     *
     * @return array<string, mixed>
     */
    public function getDebugInfo(): array
    {
        $info = [
            'start.time' => $this->start ?: -INF,
            'end.time'   => $this->end ?: -INF,
            'duration'   => $this->getOverallDuration(),
        ];

        foreach ($this->phases as $phase => $timer) {
            $timer['duration'] = $this->getPhaseDuration($phase);

            $info['phases'][$phase] = $timer;
        }

        return $info;
    }
}
