<?php

namespace Georgeff\Kernel\DI\Profile;

use Georgeff\Kernel\Profiler\Phase;
use Georgeff\Kernel\Exception\ProfilerException;
use Georgeff\Kernel\Contract\DebuggableInterface;

/**
 * @internal
 */
final class ServiceProfile implements DebuggableInterface
{
    /**
     * @var list<Phase>
     */
    private array $resolutions = [];

    private ?Phase $resolving = null;

    /**
     * @var array<array-key, mixed>
     */
    private array $debugInfo = [];

    public function __construct(public readonly string $id) {}

    public function resolving(): self
    {
        $this->resolving = new Phase('__resolution__');

        $this->resolving->start();

        return $this;
    }

    public function resolved(mixed $instance): self
    {
        ProfilerException::throwIf(
            null === $this->resolving,
            'Cannot call resolved for a service that has not started resolving'
        );

        assert(null !== $this->resolving);

        $this->resolving->stop();

        $this->resolutions[] = $this->resolving;

        $this->resolving = null;

        if ($instance instanceof DebuggableInterface) {
            $this->debugInfo = $instance->getDebugInfo();
        }

        return $this;
    }

    public function getDebugInfo(): array
    {
        $output = [
            'count'    => 0,
            'duration' => 0.0,
            'memory'   => 0,
        ];

        $resolutions = [];

        foreach ($this->resolutions as $resolution) {
            $output['count'] += 1;

            if (!$resolution->isIncomplete()) {
                $output['duration'] += $resolution->getDuration();
                $output['memory'] += $resolution->getMemoryUsage();
            }


            $resolutions[] = $resolution->getDebugInfo();
        }

        $output['resolutions'] = $resolutions;

        if ([] !== $this->debugInfo) {
            $output['debug.info'] = $this->debugInfo;
        }

        return $output;
    }
}
