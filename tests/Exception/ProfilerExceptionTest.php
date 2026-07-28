<?php

namespace Georgeff\Kernel\Test\Exception;

use Georgeff\Kernel\Exception\KernelExceptionInterface;
use Georgeff\Kernel\Exception\ProfilerException;
use PHPUnit\Framework\TestCase;

class ProfilerExceptionTest extends TestCase
{
    public function test_it_implements_kernel_exception_interface(): void
    {
        $exception = new ProfilerException('something went wrong');

        $this->assertInstanceOf(KernelExceptionInterface::class, $exception);
    }
}
