<?php

namespace Georgeff\Kernel\Hook;

use Georgeff\Kernel\KernelInterface;

/**
 * @internal
 */
final class HookRepository
{
    /**
     * @var array<callable(KernelInterface): void>
     */
    private array $preBoot = [];

    /**
     * @var array<callable(KernelInterface): void>
     */
    private array $afterBoot = [];

    /**
     * @var array<callable(KernelInterface): void>
     */
    private array $preShutdown = [];

    /**
     * @var array<callable(KernelInterface): void>
     */
    private array $afterShutdown = [];

    /**
     * @param callable(KernelInterface): void $callback
     */
    public function onBooting(callable $callback): self
    {
        $this->preBoot[] = $callback;

        return $this;
    }

    /**
     * @return array<callable(KernelInterface): void>
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

    /**
     * @return array<callable(KernelInterface): void>
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

    /**
     * @return array<callable(KernelInterface): void>
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

    /**
     * @return array<callable(KernelInterface): void>
     */
    public function getAfterShutdownCallbacks(): array
    {
        return $this->afterShutdown;
    }
}
