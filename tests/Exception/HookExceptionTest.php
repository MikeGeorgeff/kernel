<?php

namespace Georgeff\Kernel\Test\Exception;

use Georgeff\Kernel\Exception\HookException;
use Georgeff\Kernel\Exception\KernelExceptionInterface;
use PHPUnit\Framework\TestCase;

class HookExceptionTest extends TestCase
{
    public function test_it_implements_kernel_exception_interface(): void
    {
        $exception = new HookException('something went wrong');

        $this->assertInstanceOf(KernelExceptionInterface::class, $exception);
    }

    // -------------------------------------------------------------------------
    // throwOnCallbackError()
    // -------------------------------------------------------------------------

    public function test_throw_on_callback_error_throws_a_hook_exception(): void
    {
        $this->expectException(HookException::class);

        HookException::throwOnCallbackError('onBooting', new \RuntimeException('boom'));
    }

    public function test_throw_on_callback_error_message_includes_the_hook_and_original_message(): void
    {
        $this->expectExceptionMessage('Hook callback for [onBooting] failed: boom');

        HookException::throwOnCallbackError('onBooting', new \RuntimeException('boom'));
    }

    public function test_throw_on_callback_error_preserves_the_original_exception_as_previous(): void
    {
        $original = new \RuntimeException('boom');

        try {
            HookException::throwOnCallbackError('onBooting', $original);
            $this->fail('Expected HookException was not thrown.');
        } catch (HookException $e) {
            $this->assertSame($original, $e->getPrevious());
        }
    }

    // -------------------------------------------------------------------------
    // throwOnCallbackErrors()
    // -------------------------------------------------------------------------

    public function test_throw_on_callback_errors_throws_a_hook_exception(): void
    {
        $this->expectException(HookException::class);

        HookException::throwOnCallbackErrors('onShutdown', [new \RuntimeException('boom')]);
    }

    public function test_throw_on_callback_errors_message_includes_the_count_and_all_messages(): void
    {
        $this->expectExceptionMessage('[2] callbacks for [onShutdown] failed: first; second');

        HookException::throwOnCallbackErrors('onShutdown', [
            new \RuntimeException('first'),
            new \RuntimeException('second'),
        ]);
    }

    public function test_throw_on_callback_errors_preserves_the_first_exception_as_previous(): void
    {
        $first  = new \RuntimeException('first');
        $second = new \RuntimeException('second');

        try {
            HookException::throwOnCallbackErrors('onShutdown', [$first, $second]);
            $this->fail('Expected HookException was not thrown.');
        } catch (HookException $e) {
            $this->assertSame($first, $e->getPrevious());
        }
    }
}
