<?php

namespace Georgeff\Kernel\Test\Exception;

use Georgeff\Kernel\Exception\KernelExceptionInterface;
use Georgeff\Kernel\Exception\ThrowHelpers;
use PHPUnit\Framework\TestCase;

class ThrowHelpersTest extends TestCase
{
    // -------------------------------------------------------------------------
    // instance()
    // -------------------------------------------------------------------------

    public function test_instance_returns_an_instance_of_the_using_class(): void
    {
        $exception = ThrowHelpersFixtureException::instance('something went wrong');

        $this->assertInstanceOf(ThrowHelpersFixtureException::class, $exception);
    }

    public function test_instance_sets_the_message(): void
    {
        $exception = ThrowHelpersFixtureException::instance('something went wrong');

        $this->assertSame('something went wrong', $exception->getMessage());
    }

    public function test_instance_without_previous_has_no_previous(): void
    {
        $exception = ThrowHelpersFixtureException::instance('something went wrong');

        $this->assertNull($exception->getPrevious());
    }

    public function test_instance_with_previous_sets_previous(): void
    {
        $previous = new \RuntimeException('original');

        $exception = ThrowHelpersFixtureException::instance('something went wrong', $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    // -------------------------------------------------------------------------
    // throw()
    // -------------------------------------------------------------------------

    public function test_throw_throws_with_message(): void
    {
        $this->expectException(ThrowHelpersFixtureException::class);
        $this->expectExceptionMessage('something went wrong');

        ThrowHelpersFixtureException::throw('something went wrong');
    }

    public function test_throw_preserves_previous(): void
    {
        $previous = new \RuntimeException('original');

        try {
            ThrowHelpersFixtureException::throw('something went wrong', $previous);
            $this->fail('Expected ThrowHelpersFixtureException was not thrown.');
        } catch (ThrowHelpersFixtureException $e) {
            $this->assertSame($previous, $e->getPrevious());
        }
    }

    // -------------------------------------------------------------------------
    // throwIf()
    // -------------------------------------------------------------------------

    public function test_throw_if_throws_when_condition_is_true(): void
    {
        $this->expectException(ThrowHelpersFixtureException::class);
        $this->expectExceptionMessage('something went wrong');

        ThrowHelpersFixtureException::throwIf(true, 'something went wrong');
    }

    public function test_throw_if_does_not_throw_when_condition_is_false(): void
    {
        ThrowHelpersFixtureException::throwIf(false, 'something went wrong');

        $this->addToAssertionCount(1);
    }

    public function test_throw_if_preserves_previous(): void
    {
        $previous = new \RuntimeException('original');

        try {
            ThrowHelpersFixtureException::throwIf(true, 'something went wrong', $previous);
            $this->fail('Expected ThrowHelpersFixtureException was not thrown.');
        } catch (ThrowHelpersFixtureException $e) {
            $this->assertSame($previous, $e->getPrevious());
        }
    }

    // -------------------------------------------------------------------------
    // throwIfNot()
    // -------------------------------------------------------------------------

    public function test_throw_if_not_throws_when_condition_is_false(): void
    {
        $this->expectException(ThrowHelpersFixtureException::class);
        $this->expectExceptionMessage('something went wrong');

        ThrowHelpersFixtureException::throwIfNot(false, 'something went wrong');
    }

    public function test_throw_if_not_does_not_throw_when_condition_is_true(): void
    {
        ThrowHelpersFixtureException::throwIfNot(true, 'something went wrong');

        $this->addToAssertionCount(1);
    }

    public function test_throw_if_not_preserves_previous(): void
    {
        $previous = new \RuntimeException('original');

        try {
            ThrowHelpersFixtureException::throwIfNot(false, 'something went wrong', $previous);
            $this->fail('Expected ThrowHelpersFixtureException was not thrown.');
        } catch (ThrowHelpersFixtureException $e) {
            $this->assertSame($previous, $e->getPrevious());
        }
    }
}

final class ThrowHelpersFixtureException extends \RuntimeException implements KernelExceptionInterface
{
    use ThrowHelpers;
}
