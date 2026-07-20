<?php

namespace Georgeff\Kernel\DI;

use Throwable;
use Georgeff\Kernel\Debug\DebuggableInterface;
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
     * @var array<class-string<ResettableInterface>, int>
     */
    private array $failures = [];

    /**
     * @var array<class-string<ResettableInterface>, array<class-string<Throwable>, string[]>>
     */
    private array $logs = [];

    /**
     * @return array{
     *      failures: array<class-string<ResettableInterface>, int>,
     *      logs: array<class-string<ResettableInterface>, array<class-string<Throwable>, string[]>>
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
     * @throws ServiceResetException
     */
    public function reset(int $failureThreshold = 3): void
    {
        foreach (array_reverse($this->services, true) as $service) {
            try {
                $service->reset();

                $this->clearServiceFailures($service::class);
            } catch (Throwable $e) {
                $threshold = $service instanceof ThresholdAwareResettableInterface
                    ? $service->getFailureThreshold()
                    : $failureThreshold;

                $this->logFailure($service::class, $e);

                if ($this->shouldFail($service::class, $threshold)) {
                    ServiceResetException::fail($service::class, $e);
                }
            }
        }
    }

    /**
     * @param class-string<ResettableInterface> $service
     */
    private function shouldFail(string $service, int $threshold): bool
    {
        $failures = $this->getFailures($service);

        return $failures >= $threshold;
    }

    /**
     * @param class-string<ResettableInterface> $service
     */
    private function logFailure(string $service, Throwable $e): void
    {
        $failures = $this->getFailures($service);

        $this->failures[$service] = $failures + 1;

        $this->logs[$service][$e::class][] = $e->getMessage();
    }

    /**
     * @param class-string<ResettableInterface> $service
     */
    private function getFailures(string $service): int
    {
        return $this->failures[$service] ?? 0;
    }

    /**
     * @param class-string<ResettableInterface> $service
     */
    private function clearServiceFailures(string $service): void
    {
        if (isset($this->failures[$service])) {
            unset($this->failures[$service]);
            unset($this->logs[$service]);
        }
    }
}
