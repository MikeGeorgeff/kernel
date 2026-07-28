<?php

namespace Georgeff\Kernel\Test\Exception;

use Georgeff\Kernel\Exception\EnvironmentException;
use Georgeff\Kernel\Exception\KernelExceptionInterface;
use PHPUnit\Framework\TestCase;

class EnvironmentExceptionTest extends TestCase
{
    public function test_it_implements_kernel_exception_interface(): void
    {
        $exception = new EnvironmentException('something went wrong');

        $this->assertInstanceOf(KernelExceptionInterface::class, $exception);
    }
}
