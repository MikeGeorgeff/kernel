<?php

namespace Georgeff\Kernel\Test\Hook;

use Georgeff\Kernel\Exception\HookException;
use Georgeff\Kernel\Exception\KernelException;
use Georgeff\Kernel\Hook\HookRepository;
use Georgeff\Kernel\KernelInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;

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

    // -------------------------------------------------------------------------
    // invokeOnBootingCallbacks() — fail-fast group
    // -------------------------------------------------------------------------

    public function test_invoke_on_booting_callbacks_calls_each_callback_with_the_kernel(): void
    {
        $repository = new HookRepository();
        $kernel     = $this->createStub(KernelInterface::class);
        $received   = [];

        $repository->onBooting(function (KernelInterface $k) use (&$received) {
            $received[] = $k;
        });

        $repository->invokeOnBootingCallbacks($kernel);

        $this->assertSame([$kernel], $received);
    }

    public function test_invoke_on_booting_callbacks_calls_callbacks_in_order(): void
    {
        $repository = new HookRepository();
        $kernel     = $this->createStub(KernelInterface::class);
        $order      = [];

        $repository->onBooting(function () use (&$order) { $order[] = 'first'; });
        $repository->onBooting(function () use (&$order) { $order[] = 'second'; });

        $repository->invokeOnBootingCallbacks($kernel);

        $this->assertSame(['first', 'second'], $order);
    }

    public function test_invoke_on_booting_callbacks_rethrows_a_kernel_exception_unwrapped(): void
    {
        $repository = new HookRepository();
        $kernel     = $this->createStub(KernelInterface::class);

        $repository->onBooting(function () {
            throw new KernelException('boom');
        });

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('boom');

        $repository->invokeOnBootingCallbacks($kernel);
    }

    public function test_invoke_on_booting_callbacks_rethrows_a_container_exception_unwrapped(): void
    {
        $repository = new HookRepository();
        $kernel     = $this->createStub(KernelInterface::class);

        $exception = new class extends \Exception implements ContainerExceptionInterface {};

        $repository->onBooting(function () use ($exception) {
            throw $exception;
        });

        $this->expectException($exception::class);

        $repository->invokeOnBootingCallbacks($kernel);
    }

    public function test_invoke_on_booting_callbacks_wraps_other_throwables_in_hook_exception(): void
    {
        $repository = new HookRepository();
        $kernel     = $this->createStub(KernelInterface::class);

        $repository->onBooting(function () {
            throw new \RuntimeException('boom');
        });

        $this->expectException(HookException::class);
        $this->expectExceptionMessage('Hook callback for [onBooting] failed: boom');

        $repository->invokeOnBootingCallbacks($kernel);
    }

    public function test_invoke_on_booting_callbacks_preserves_the_original_exception_as_previous(): void
    {
        $repository = new HookRepository();
        $kernel     = $this->createStub(KernelInterface::class);
        $original   = new \RuntimeException('boom');

        $repository->onBooting(function () use ($original) {
            throw $original;
        });

        try {
            $repository->invokeOnBootingCallbacks($kernel);
            $this->fail('Expected HookException was not thrown.');
        } catch (HookException $e) {
            $this->assertSame($original, $e->getPrevious());
        }
    }

    public function test_invoke_on_booting_callbacks_stops_at_the_first_failure(): void
    {
        $repository = new HookRepository();
        $kernel     = $this->createStub(KernelInterface::class);
        $secondRan  = false;

        $repository->onBooting(function () {
            throw new \RuntimeException('boom');
        });
        $repository->onBooting(function () use (&$secondRan) {
            $secondRan = true;
        });

        try {
            $repository->invokeOnBootingCallbacks($kernel);
        } catch (HookException) {
        }

        $this->assertFalse($secondRan);
    }

    // -------------------------------------------------------------------------
    // invokeOnBootedCallbacks() — same fail-fast group, lighter coverage
    // -------------------------------------------------------------------------

    public function test_invoke_on_booted_callbacks_calls_each_callback_with_the_kernel(): void
    {
        $repository = new HookRepository();
        $kernel     = $this->createStub(KernelInterface::class);
        $received   = [];

        $repository->onBooted(function (KernelInterface $k) use (&$received) {
            $received[] = $k;
        });

        $repository->invokeOnBootedCallbacks($kernel);

        $this->assertSame([$kernel], $received);
    }

    public function test_invoke_on_booted_callbacks_wraps_failures_in_hook_exception(): void
    {
        $repository = new HookRepository();
        $kernel     = $this->createStub(KernelInterface::class);

        $repository->onBooted(function () {
            throw new \RuntimeException('boom');
        });

        $this->expectException(HookException::class);
        $this->expectExceptionMessage('Hook callback for [onBooted] failed: boom');

        $repository->invokeOnBootedCallbacks($kernel);
    }

    // -------------------------------------------------------------------------
    // invokeOnShutdownCallbacks() — continue-and-aggregate group
    // -------------------------------------------------------------------------

    public function test_invoke_on_shutdown_callbacks_calls_each_callback_with_the_kernel(): void
    {
        $repository = new HookRepository();
        $kernel     = $this->createStub(KernelInterface::class);
        $received   = [];

        $repository->onShutdown(function (KernelInterface $k) use (&$received) {
            $received[] = $k;
        });

        $repository->invokeOnShutdownCallbacks($kernel);

        $this->assertSame([$kernel], $received);
    }

    public function test_invoke_on_shutdown_callbacks_does_not_throw_when_no_callback_fails(): void
    {
        $repository = new HookRepository();
        $kernel     = $this->createStub(KernelInterface::class);
        $called     = false;

        $repository->onShutdown(function () use (&$called) { $called = true; });

        $repository->invokeOnShutdownCallbacks($kernel);

        $this->assertTrue($called);
    }

    public function test_invoke_on_shutdown_callbacks_runs_every_callback_even_if_an_earlier_one_throws(): void
    {
        $repository = new HookRepository();
        $kernel     = $this->createStub(KernelInterface::class);
        $secondRan  = false;

        $repository->onShutdown(function () {
            throw new \RuntimeException('boom');
        });
        $repository->onShutdown(function () use (&$secondRan) {
            $secondRan = true;
        });

        try {
            $repository->invokeOnShutdownCallbacks($kernel);
        } catch (HookException) {
        }

        $this->assertTrue($secondRan);
    }

    public function test_invoke_on_shutdown_callbacks_throws_hook_exception_when_a_callback_fails(): void
    {
        $repository = new HookRepository();
        $kernel     = $this->createStub(KernelInterface::class);

        $repository->onShutdown(function () {
            throw new \RuntimeException('boom');
        });

        $this->expectException(HookException::class);

        $repository->invokeOnShutdownCallbacks($kernel);
    }

    public function test_invoke_on_shutdown_callbacks_aggregate_message_includes_the_failure_count_and_messages(): void
    {
        $repository = new HookRepository();
        $kernel     = $this->createStub(KernelInterface::class);

        $repository->onShutdown(function () { throw new \RuntimeException('first failure'); });
        $repository->onShutdown(function () { throw new \RuntimeException('second failure'); });

        $this->expectException(HookException::class);
        $this->expectExceptionMessage('[2] callbacks for [onShutdown] failed: first failure; second failure');

        $repository->invokeOnShutdownCallbacks($kernel);
    }

    public function test_invoke_on_shutdown_callbacks_preserves_the_first_failure_as_previous(): void
    {
        $repository = new HookRepository();
        $kernel     = $this->createStub(KernelInterface::class);
        $first      = new \RuntimeException('first failure');
        $second     = new \RuntimeException('second failure');

        $repository->onShutdown(function () use ($first) { throw $first; });
        $repository->onShutdown(function () use ($second) { throw $second; });

        try {
            $repository->invokeOnShutdownCallbacks($kernel);
            $this->fail('Expected HookException was not thrown.');
        } catch (HookException $e) {
            $this->assertSame($first, $e->getPrevious());
        }
    }

    public function test_invoke_on_shutdown_callbacks_does_not_wrap_a_kernel_exception_still_reports_it(): void
    {
        $repository = new HookRepository();
        $kernel     = $this->createStub(KernelInterface::class);
        $original   = new KernelException('boom');

        $repository->onShutdown(function () use ($original) { throw $original; });

        try {
            $repository->invokeOnShutdownCallbacks($kernel);
            $this->fail('Expected HookException was not thrown.');
        } catch (HookException $e) {
            $this->assertSame($original, $e->getPrevious());
        }
    }

    // -------------------------------------------------------------------------
    // invokeAfterShutdownCallbacks() — same continue-and-aggregate group, lighter coverage
    // -------------------------------------------------------------------------

    public function test_invoke_after_shutdown_callbacks_calls_each_callback_with_the_kernel(): void
    {
        $repository = new HookRepository();
        $kernel     = $this->createStub(KernelInterface::class);
        $received   = [];

        $repository->afterShutdown(function (KernelInterface $k) use (&$received) {
            $received[] = $k;
        });

        $repository->invokeAfterShutdownCallbacks($kernel);

        $this->assertSame([$kernel], $received);
    }

    public function test_invoke_after_shutdown_callbacks_runs_every_callback_even_if_an_earlier_one_throws(): void
    {
        $repository = new HookRepository();
        $kernel     = $this->createStub(KernelInterface::class);
        $secondRan  = false;

        $repository->afterShutdown(function () {
            throw new \RuntimeException('boom');
        });
        $repository->afterShutdown(function () use (&$secondRan) {
            $secondRan = true;
        });

        try {
            $repository->invokeAfterShutdownCallbacks($kernel);
        } catch (HookException) {
        }

        $this->assertTrue($secondRan);
    }

    // -------------------------------------------------------------------------
    // gc()
    // -------------------------------------------------------------------------

    public function test_gc_clears_pending_on_booting_callbacks(): void
    {
        $repository = new HookRepository();
        $repository->onBooting(fn(KernelInterface $k) => null);

        $repository->gc();

        $this->assertSame([], $repository->getOnBootingCallbacks());
    }

    public function test_gc_clears_pending_on_booted_callbacks(): void
    {
        $repository = new HookRepository();
        $repository->onBooted(fn(KernelInterface $k) => null);

        $repository->gc();

        $this->assertSame([], $repository->getOnBootedCallbacks());
    }

    public function test_gc_does_not_clear_pending_on_shutdown_callbacks(): void
    {
        $repository = new HookRepository();
        $callback   = fn(KernelInterface $k) => null;
        $repository->onShutdown($callback);

        $repository->gc();

        $this->assertSame([$callback], $repository->getOnShutdownCallbacks());
    }

    public function test_gc_does_not_clear_pending_after_shutdown_callbacks(): void
    {
        $repository = new HookRepository();
        $callback   = fn(KernelInterface $k) => null;
        $repository->afterShutdown($callback);

        $repository->gc();

        $this->assertSame([$callback], $repository->getAfterShutdownCallbacks());
    }

    public function test_gc_called_from_within_an_on_booted_callback_does_not_skip_later_on_booted_callbacks(): void
    {
        $repository = new HookRepository();
        $kernel     = $this->createStub(KernelInterface::class);
        $secondRan  = false;

        $repository->onBooted(function () use ($repository) {
            $repository->gc();
        });
        $repository->onBooted(function () use (&$secondRan) {
            $secondRan = true;
        });

        $repository->invokeOnBootedCallbacks($kernel);

        $this->assertTrue($secondRan);
    }
}
