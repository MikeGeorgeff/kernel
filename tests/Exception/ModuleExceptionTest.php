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

    public function test_throw_if_not_throws_when_condition_is_false(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage('something went wrong');

        ModuleException::throwIfNot(false, 'something went wrong');
    }

    public function test_throw_if_not_does_not_throw_when_condition_is_true(): void
    {
        ModuleException::throwIfNot(true, 'something went wrong');

        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // throwOnRegistrationError()
    // -------------------------------------------------------------------------

    public function test_throw_on_registration_error_throws_a_module_exception(): void
    {
        $this->expectException(ModuleException::class);

        ModuleException::throwOnRegistrationError('MyModule', new \RuntimeException('boom'));
    }

    public function test_throw_on_registration_error_message_includes_the_module_and_original_message(): void
    {
        $this->expectExceptionMessage('Failed to register module [MyModule]: boom');

        ModuleException::throwOnRegistrationError('MyModule', new \RuntimeException('boom'));
    }

    public function test_throw_on_registration_error_preserves_the_original_exception_as_previous(): void
    {
        $original = new \RuntimeException('boom');

        try {
            ModuleException::throwOnRegistrationError('MyModule', $original);
            $this->fail('Expected ModuleException was not thrown');
        } catch (ModuleException $e) {
            $this->assertSame($original, $e->getPrevious());
        }
    }

    // -------------------------------------------------------------------------
    // throwOnBootError()
    // -------------------------------------------------------------------------

    public function test_throw_on_boot_error_throws_a_module_exception(): void
    {
        $this->expectException(ModuleException::class);

        ModuleException::throwOnBootError('MyModule', new \RuntimeException('boom'));
    }

    public function test_throw_on_boot_error_message_includes_the_module_and_original_message(): void
    {
        $this->expectExceptionMessage('Failed to boot module [MyModule]: boom');

        ModuleException::throwOnBootError('MyModule', new \RuntimeException('boom'));
    }

    public function test_throw_on_boot_error_preserves_the_original_exception_as_previous(): void
    {
        $original = new \RuntimeException('boom');

        try {
            ModuleException::throwOnBootError('MyModule', $original);
            $this->fail('Expected ModuleException was not thrown');
        } catch (ModuleException $e) {
            $this->assertSame($original, $e->getPrevious());
        }
    }
}
