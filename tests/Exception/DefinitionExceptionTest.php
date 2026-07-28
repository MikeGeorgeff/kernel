<?php

namespace Georgeff\Kernel\Test\Exception;

use Georgeff\Kernel\Exception\DefinitionException;
use Georgeff\Kernel\Exception\KernelExceptionInterface;
use PHPUnit\Framework\TestCase;

class DefinitionExceptionTest extends TestCase
{
    public function test_it_implements_kernel_exception_interface(): void
    {
        $exception = new DefinitionException('something went wrong');

        $this->assertInstanceOf(KernelExceptionInterface::class, $exception);
    }
}
