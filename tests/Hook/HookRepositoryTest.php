<?php

namespace Georgeff\Kernel\Test\Hook;

use Georgeff\Kernel\Hook\HookRepository;
use Georgeff\Kernel\KernelInterface;
use PHPUnit\Framework\TestCase;

class HookRepositoryTest extends TestCase
{
    public function test_on_booting_stores_callback(): void
    {
        $repository = new HookRepository();
        $callback = fn(KernelInterface $k) => null;

        $repository->onBooting($callback);

        $this->assertSame([$callback], $repository->getOnBootingCallbacks());
    }

    public function test_on_booting_stores_multiple_callbacks_in_order(): void
    {
        $repository = new HookRepository();
        $first  = fn(KernelInterface $k) => null;
        $second = fn(KernelInterface $k) => null;

        $repository->onBooting($first)->onBooting($second);

        $this->assertSame([$first, $second], $repository->getOnBootingCallbacks());
    }

    public function test_on_booted_stores_callback(): void
    {
        $repository = new HookRepository();
        $callback = fn(KernelInterface $k) => null;

        $repository->onBooted($callback);

        $this->assertSame([$callback], $repository->getOnBootedCallbacks());
    }

    public function test_on_booted_stores_multiple_callbacks_in_order(): void
    {
        $repository = new HookRepository();
        $first  = fn(KernelInterface $k) => null;
        $second = fn(KernelInterface $k) => null;

        $repository->onBooted($first)->onBooted($second);

        $this->assertSame([$first, $second], $repository->getOnBootedCallbacks());
    }

    public function test_on_shutdown_stores_callback(): void
    {
        $repository = new HookRepository();
        $callback = fn(KernelInterface $k) => null;

        $repository->onShutdown($callback);

        $this->assertSame([$callback], $repository->getOnShutdownCallbacks());
    }

    public function test_on_shutdown_stores_multiple_callbacks_in_order(): void
    {
        $repository = new HookRepository();
        $first  = fn(KernelInterface $k) => null;
        $second = fn(KernelInterface $k) => null;

        $repository->onShutdown($first)->onShutdown($second);

        $this->assertSame([$first, $second], $repository->getOnShutdownCallbacks());
    }

    public function test_after_shutdown_stores_callback(): void
    {
        $repository = new HookRepository();
        $callback = fn(KernelInterface $k) => null;

        $repository->afterShutdown($callback);

        $this->assertSame([$callback], $repository->getAfterShutdownCallbacks());
    }

    public function test_after_shutdown_stores_multiple_callbacks_in_order(): void
    {
        $repository = new HookRepository();
        $first  = fn(KernelInterface $k) => null;
        $second = fn(KernelInterface $k) => null;

        $repository->afterShutdown($first)->afterShutdown($second);

        $this->assertSame([$first, $second], $repository->getAfterShutdownCallbacks());
    }

    public function test_each_hook_type_is_stored_independently(): void
    {
        $repository = new HookRepository();
        $callback = fn(KernelInterface $k) => null;

        $repository->onBooting($callback);

        $this->assertSame([$callback], $repository->getOnBootingCallbacks());
        $this->assertSame([], $repository->getOnBootedCallbacks());
        $this->assertSame([], $repository->getOnShutdownCallbacks());
        $this->assertSame([], $repository->getAfterShutdownCallbacks());
    }

    public function test_get_callbacks_returns_empty_array_by_default(): void
    {
        $repository = new HookRepository();

        $this->assertSame([], $repository->getOnBootingCallbacks());
        $this->assertSame([], $repository->getOnBootedCallbacks());
        $this->assertSame([], $repository->getOnShutdownCallbacks());
        $this->assertSame([], $repository->getAfterShutdownCallbacks());
    }
}
