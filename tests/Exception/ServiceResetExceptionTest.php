<?php

namespace Georgeff\Kernel\Test\Exception;

use Georgeff\Kernel\Exception\KernelExceptionInterface;
use Georgeff\Kernel\Exception\ServiceResetException;
use PHPUnit\Framework\TestCase;

class ServiceResetExceptionTest extends TestCase
{
    public function test_it_implements_kernel_exception_interface(): void
    {
        $exception = new ServiceResetException('something went wrong');

        $this->assertInstanceOf(KernelExceptionInterface::class, $exception);
    }

    // -------------------------------------------------------------------------
    // fail()
    // -------------------------------------------------------------------------
    //
    // Message format and previous-exception preservation are already covered
    // via ServiceResetter's own threshold tests in tests/DI/ServiceResetterTest.php.

    public function test_fail_throws_a_service_reset_exception(): void
    {
        $this->expectException(ServiceResetException::class);

        ServiceResetException::fail('my.service', new \RuntimeException('boom'));
    }
}
