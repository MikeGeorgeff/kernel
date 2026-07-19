<?php

namespace Georgeff\Kernel\Test\Exception;

use Georgeff\Kernel\Exception\KernelException;
use PHPUnit\Framework\TestCase;

class KernelExceptionTest extends TestCase
{
    public function test_throw_throws_with_message(): void
    {
        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('something went wrong');

        KernelException::throw('something went wrong');
    }

    public function test_throw_if_throws_when_condition_is_true(): void
    {
        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('something went wrong');

        KernelException::throwIf(true, 'something went wrong');
    }

    public function test_throw_if_does_not_throw_when_condition_is_false(): void
    {
        KernelException::throwIf(false, 'something went wrong');

        $this->addToAssertionCount(1);
    }

    public function test_throw_if_not_throws_when_condition_is_false(): void
    {
        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('something went wrong');

        KernelException::throwIfNot(false, 'something went wrong');
    }

    public function test_throw_if_not_does_not_throw_when_condition_is_true(): void
    {
        KernelException::throwIfNot(true, 'something went wrong');

        $this->addToAssertionCount(1);
    }
}
