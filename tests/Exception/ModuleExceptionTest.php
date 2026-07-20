<?php

namespace Georgeff\Kernel\Test\Exception;

use Georgeff\Kernel\Exception\KernelExceptionInterface;
use Georgeff\Kernel\Exception\ModuleException;
use PHPUnit\Framework\TestCase;

class ModuleExceptionTest extends TestCase
{
    public function test_it_implements_kernel_exception_interface(): void
    {
        $exception = new ModuleException('something went wrong');

        $this->assertInstanceOf(KernelExceptionInterface::class, $exception);
    }

    public function test_throw_if_throws_when_condition_is_true(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage('something went wrong');

        ModuleException::throwIf(true, 'something went wrong');
    }

    public function test_throw_if_does_not_throw_when_condition_is_false(): void
    {
        ModuleException::throwIf(false, 'something went wrong');

        $this->addToAssertionCount(1);
    }

    public function test_throw_on_registration_error_throws_a_module_exception(): void
    {
        $this->expectException(ModuleException::class);

        ModuleException::throwOnRegistrationError(new \RuntimeException('boom'));
    }

    public function test_throw_on_registration_error_message_includes_the_original_message(): void
    {
        $this->expectExceptionMessage('Module registration error: boom');

        ModuleException::throwOnRegistrationError(new \RuntimeException('boom'));
    }

    public function test_throw_on_registration_error_preserves_the_original_exception_as_previous(): void
    {
        $original = new \RuntimeException('boom');

        try {
            ModuleException::throwOnRegistrationError($original);
            $this->fail('Expected ModuleException was not thrown');
        } catch (ModuleException $e) {
            $this->assertSame($original, $e->getPrevious());
        }
    }
}
