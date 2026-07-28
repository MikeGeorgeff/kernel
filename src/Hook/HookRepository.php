<?php

namespace Georgeff\Kernel\Hook;

use Throwable;
use Georgeff\Kernel\KernelInterface;
use Georgeff\Kernel\Exception\HookException;
use Psr\Container\ContainerExceptionInterface;
use Georgeff\Kernel\Exception\KernelExceptionInterface;

/**
 * @internal
 */
final class HookRepository
{
    /**
     * @var list<callable(KernelInterface): void>
     */
    private array $preBoot = [];

    /**
     * @var list<callable(KernelInterface): void>
     */
    private array $afterBoot = [];

    /**
     * @var list<callable(KernelInterface): void>
     */
    private array $preShutdown = [];

    /**
     * @var list<callable(KernelInterface): void>
     */
    private array $afterShutdown = [];

    public function gc(): void
    {
        $this->preBoot   = [];
        $this->afterBoot = [];
    }

    /**
     * @param list<callable(KernelInterface): void> $callbacks
     */
    private function invoke(array $callbacks, KernelInterface $kernel, string $hook, bool $continueOnFailure): void
    {
        /** @var Throwable[] */
        $failures = [];

        foreach ($callbacks as $callback) {
            try {
                $callback($kernel);
            } catch (KernelExceptionInterface|ContainerExceptionInterface $e) {
                $continueOnFailure ? $failures[] = $e : throw $e;
            } catch (Throwable $e) {
                $continueOnFailure ? $failures[] = $e : HookException::throwOnCallbackError($hook, $e);
            }
        }

        if ([] !== $failures) {
            HookException::throwOnCallbackErrors($hook, $failures);
        }
    }

    /**
     * @param callable(KernelInterface): void $callback
     */
    public function onBooting(callable $callback): self
    {
        $this->preBoot[] = $callback;

        return $this;
    }

    public function invokeOnBootingCallbacks(KernelInterface $kernel): void
    {
        $this->invoke($this->preBoot, $kernel, 'onBooting', false);
    }

    /**
     * @return list<callable(KernelInterface): void>
     */
    public function getOnBootingCallbacks(): array
    {
        return $this->preBoot;
    }

    /**
     * @param callable(KernelInterface): void $callback
     */
    public function onBooted(callable $callback): self
    {
        $this->afterBoot[] = $callback;

        return $this;
    }

    public function invokeOnBootedCallbacks(KernelInterface $kernel): void
    {
        $this->invoke($this->afterBoot, $kernel, 'onBooted', false);
    }

    /**
     * @return list<callable(KernelInterface): void>
     */
    public function getOnBootedCallbacks(): array
    {
        return $this->afterBoot;
    }

    /**
     * @param callable(KernelInterface): void $callback
     */
    public function onShutdown(callable $callback): self
    {
        $this->preShutdown[] = $callback;

        return $this;
    }

    public function invokeOnShutdownCallbacks(KernelInterface $kernel): void
    {
        $this->invoke($this->preShutdown, $kernel, 'onShutdown', true);
    }

    /**
     * @return list<callable(KernelInterface): void>
     */
    public function getOnShutdownCallbacks(): array
    {
        return $this->preShutdown;
    }

    /**
     * @param callable(KernelInterface): void $callback
     */
    public function afterShutdown(callable $callback): self
    {
        $this->afterShutdown[] = $callback;

        return $this;
    }

    public function invokeAfterShutdownCallbacks(KernelInterface $kernel): void
    {
        $this->invoke($this->afterShutdown, $kernel, 'afterShutdown', true);
    }

    /**
     * @return list<callable(KernelInterface): void>
     */
    public function getAfterShutdownCallbacks(): array
    {
        return $this->afterShutdown;
    }
}
