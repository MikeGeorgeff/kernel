<?php

namespace Georgeff\Kernel\DI;

use Throwable;
use Georgeff\Kernel\Contract\DebuggableInterface;
use Georgeff\Kernel\Contract\ResettableInterface;
use Georgeff\Kernel\Exception\ServiceResetException;
use Georgeff\Kernel\Contract\ThresholdAwareResettableInterface;

/**
 * @internal
 */
final class ServiceResetter implements DebuggableInterface
{
    /**
     * @var array<string, ResettableInterface>
     */
    private array $services = [];

    /**
     * @var array<string, int>
     */
    private array $failures = [];

    /**
     * @var array<string, array<class-string<Throwable>, string[]>>
     */
    private array $logs = [];

    public function gc(): void
    {
        $this->services = [];
        $this->failures = [];
        $this->logs     = [];
    }

    /**
     * @return array{
     *      failures: array<string, int>,
     *      logs: array<string, array<class-string<Throwable>, string[]>>
     * }
     */
    public function getDebugInfo(): array
    {
        return [
            'failures' => $this->failures,
            'logs'     => $this->logs,
        ];
    }

    public function add(string $id, ResettableInterface $service): void
    {
        $this->services[$id] = $service;
    }

    /**
     * @param null|string[] $ids
     *
     * @throws ServiceResetException
     */
    public function reset(int $failureThreshold = 3, ?array $ids = null): void
    {
        $toReset = null === $ids
            ? $this->services
            : array_intersect_key($this->services, array_flip($ids));

        $breaches = [];

        foreach ($toReset as $id => $service) {
            try {
                $service->reset();

                $this->clearServiceFailures($id);
            } catch (Throwable $e) {
                $threshold = $service instanceof ThresholdAwareResettableInterface
                    ? $service->getFailureThreshold()
                    : $failureThreshold;

                $this->logFailure($id, $e);

                if ($this->shouldFail($id, $threshold)) {
                    $breaches[$id] = $e;
                }
            }
        }

        if ([] !== $breaches) {
            ServiceResetException::failMany($breaches);
        }
    }

    private function shouldFail(string $id, int $threshold): bool
    {
        $failures = $this->getFailures($id);

        return $failures >= $threshold;
    }

    private function logFailure(string $id, Throwable $e): void
    {
        $failures = $this->getFailures($id);

        $this->failures[$id] = $failures + 1;

        $this->logs[$id][$e::class][] = $e->getMessage();
    }

    private function getFailures(string $id): int
    {
        return $this->failures[$id] ?? 0;
    }

    private function clearServiceFailures(string $id): void
    {
        if (isset($this->failures[$id])) {
            unset($this->failures[$id]);
            unset($this->logs[$id]);
        }
    }
}
