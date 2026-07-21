<?php

namespace Georgeff\Kernel\Test\Exception;

use Georgeff\Kernel\Exception\ConfigException;
use Georgeff\Kernel\Exception\KernelExceptionInterface;
use PHPUnit\Framework\TestCase;

class ConfigExceptionTest extends TestCase
{
    public function test_it_implements_kernel_exception_interface(): void
    {
        $exception = new ConfigException('something went wrong');

        $this->assertInstanceOf(KernelExceptionInterface::class, $exception);
    }
}
