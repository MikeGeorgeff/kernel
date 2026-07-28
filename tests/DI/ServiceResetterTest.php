<?php

namespace Georgeff\Kernel\Test\DI;

use Georgeff\Kernel\Contract\ResettableInterface;
use Georgeff\Kernel\Contract\ThresholdAwareResettableInterface;
use Georgeff\Kernel\DI\ServiceResetter;
use Georgeff\Kernel\Exception\ServiceResetException;
use PHPUnit\Framework\TestCase;

class ServiceResetterTest extends TestCase
{
    public function test_reset_is_a_no_op_when_nothing_was_added(): void
    {
        $resetter = new ServiceResetter();

        $resetter->reset();

        $this->addToAssertionCount(1);
    }

    public function test_reset_calls_reset_on_an_added_service(): void
    {
        $service = new class implements ResettableInterface {
            public bool $wasReset = false;
            public function reset(): void { $this->wasReset = true; }
        };

        $resetter = new ServiceResetter();
        $resetter->add('my.service', $service);
        $resetter->reset();

        $this->assertTrue($service->wasReset);
    }

    public function test_reset_calls_reset_on_every_added_service(): void
    {
        $serviceA = new class implements ResettableInterface {
            public bool $wasReset = false;
            public function reset(): void { $this->wasReset = true; }
        };

        $serviceB = new class implements ResettableInterface {
            public bool $wasReset = false;
            public function reset(): void { $this->wasReset = true; }
        };

        $resetter = new ServiceResetter();
        $resetter->add('service.a', $serviceA);
        $resetter->add('service.b', $serviceB);
        $resetter->reset();

        $this->assertTrue($serviceA->wasReset);
        $this->assertTrue($serviceB->wasReset);
    }

    public function test_reset_resets_two_different_instances_of_the_same_class_independently(): void
    {
        $first = new class implements ResettableInterface {
            public int $count = 1;
            public function reset(): void { $this->count = 0; }
        };

        $second = clone $first;
        $second->count = 2;

        $resetter = new ServiceResetter();
        $resetter->add('service.first', $first);
        $resetter->add('service.second', $second);
        $resetter->reset();

        $this->assertSame(0, $first->count);
        $this->assertSame(0, $second->count);
    }

    public function test_add_with_the_same_id_overwrites_the_previous_entry(): void
    {
        $calls = 0;

        $first = new class($calls) implements ResettableInterface {
            public function __construct(private int &$calls) {}
            public function reset(): void { $this->calls++; }
        };

        $second = new class($calls) implements ResettableInterface {
            public function __construct(private int &$calls) {}
            public function reset(): void { $this->calls++; }
        };

        $resetter = new ServiceResetter();
        $resetter->add('my.service', $first);
        $resetter->add('my.service', $second);
        $resetter->reset();

        $this->assertSame(1, $calls);
    }

    public function test_reset_does_not_call_reset_twice_on_the_same_instance(): void
    {
        $calls = 0;

        $service = new class($calls) implements ResettableInterface {
            public function __construct(private int &$calls) {}
            public function reset(): void { $this->calls++; }
        };

        $resetter = new ServiceResetter();
        $resetter->add('my.service', $service);
        $resetter->add('my.service', $service);
        $resetter->reset();

        $this->assertSame(1, $calls);
    }

    // -------------------------------------------------------------------------
    // reset() with $ids — scoped reset
    // -------------------------------------------------------------------------

    public function test_reset_with_ids_only_resets_the_matching_services(): void
    {
        $serviceA = new class implements ResettableInterface {
            public bool $wasReset = false;
            public function reset(): void { $this->wasReset = true; }
        };

        $serviceB = new class implements ResettableInterface {
            public bool $wasReset = false;
            public function reset(): void { $this->wasReset = true; }
        };

        $resetter = new ServiceResetter();
        $resetter->add('service.a', $serviceA);
        $resetter->add('service.b', $serviceB);

        $resetter->reset(3, ['service.a']);

        $this->assertTrue($serviceA->wasReset);
        $this->assertFalse($serviceB->wasReset);
    }

    public function test_reset_with_ids_resets_every_matching_service(): void
    {
        $serviceA = new class implements ResettableInterface {
            public bool $wasReset = false;
            public function reset(): void { $this->wasReset = true; }
        };

        $serviceB = new class implements ResettableInterface {
            public bool $wasReset = false;
            public function reset(): void { $this->wasReset = true; }
        };

        $serviceC = new class implements ResettableInterface {
            public bool $wasReset = false;
            public function reset(): void { $this->wasReset = true; }
        };

        $resetter = new ServiceResetter();
        $resetter->add('service.a', $serviceA);
        $resetter->add('service.b', $serviceB);
        $resetter->add('service.c', $serviceC);

        $resetter->reset(3, ['service.a', 'service.c']);

        $this->assertTrue($serviceA->wasReset);
        $this->assertFalse($serviceB->wasReset);
        $this->assertTrue($serviceC->wasReset);
    }

    public function test_reset_with_ids_ignores_an_id_that_was_never_added(): void
    {
        $service = new class implements ResettableInterface {
            public bool $wasReset = false;
            public function reset(): void { $this->wasReset = true; }
        };

        $resetter = new ServiceResetter();
        $resetter->add('service.a', $service);

        $resetter->reset(3, ['service.unknown']);

        $this->assertFalse($service->wasReset);
    }

    public function test_reset_with_a_null_ids_array_resets_everything(): void
    {
        $serviceA = new class implements ResettableInterface {
            public bool $wasReset = false;
            public function reset(): void { $this->wasReset = true; }
        };

        $serviceB = new class implements ResettableInterface {
            public bool $wasReset = false;
            public function reset(): void { $this->wasReset = true; }
        };

        $resetter = new ServiceResetter();
        $resetter->add('service.a', $serviceA);
        $resetter->add('service.b', $serviceB);

        $resetter->reset(3, null);

        $this->assertTrue($serviceA->wasReset);
        $this->assertTrue($serviceB->wasReset);
    }

    public function test_reset_with_an_explicit_empty_ids_array_resets_nothing(): void
    {
        $serviceA = new class implements ResettableInterface {
            public bool $wasReset = false;
            public function reset(): void { $this->wasReset = true; }
        };

        $resetter = new ServiceResetter();
        $resetter->add('service.a', $serviceA);

        // An empty (but non-null) $ids is a real, deliberate filter that matches
        // nothing — distinct from omitting $ids entirely, which resets everything.
        // This is the exact distinction Kernel::resetShared() relies on: a
        // requested tag that matches zero services must produce this case, not
        // fall back to resetting everything.
        $resetter->reset(3, []);

        $this->assertFalse($serviceA->wasReset);
    }

    // -------------------------------------------------------------------------
    // failure threshold — callsite $failureThreshold
    // -------------------------------------------------------------------------

    public function test_reset_does_not_throw_while_failures_stay_below_the_callsite_threshold(): void
    {
        $service = new class implements ResettableInterface {
            public function reset(): void { throw new \RuntimeException('boom'); }
        };

        $resetter = new ServiceResetter();
        $resetter->add('my.service', $service);

        $resetter->reset(3);

        $this->addToAssertionCount(1);
    }

    public function test_reset_throws_service_reset_exception_once_failures_reach_the_callsite_threshold(): void
    {
        $service = new class implements ResettableInterface {
            public function reset(): void { throw new \RuntimeException('boom'); }
        };

        $resetter = new ServiceResetter();
        $resetter->add('my.service', $service);

        $this->expectException(ServiceResetException::class);

        $resetter->reset(1);
    }

    public function test_reset_runs_every_service_to_completion_before_throwing(): void
    {
        $callOrder = [];

        $failingA = new class($callOrder) implements ResettableInterface {
            public function __construct(private array &$callOrder) {}
            public function reset(): void
            {
                $this->callOrder[] = 'a';
                throw new \RuntimeException('boom a');
            }
        };

        $healthy = new class($callOrder) implements ResettableInterface {
            public function __construct(private array &$callOrder) {}
            public function reset(): void
            {
                $this->callOrder[] = 'healthy';
            }
        };

        $failingB = new class($callOrder) implements ResettableInterface {
            public function __construct(private array &$callOrder) {}
            public function reset(): void
            {
                $this->callOrder[] = 'b';
                throw new \RuntimeException('boom b');
            }
        };

        $resetter = new ServiceResetter();
        $resetter->add('service.a', $failingA);
        $resetter->add('service.healthy', $healthy);
        $resetter->add('service.b', $failingB);

        try {
            // Threshold of 1 means both service.a and service.b breach on
            // their first failure. A fail-fast implementation would stop
            // after service.a and never call reset() on service.healthy or
            // service.b.
            $resetter->reset(1);
            $this->fail('Expected ServiceResetException was not thrown.');
        } catch (ServiceResetException $e) {
            $this->assertStringContainsString('[service.a]', $e->getMessage());
            $this->assertStringContainsString('[service.b]', $e->getMessage());
        }

        $this->assertSame(['a', 'healthy', 'b'], $callOrder);
    }

    public function test_reset_accumulates_failures_across_calls_until_the_callsite_threshold_is_reached(): void
    {
        $service = new class implements ResettableInterface {
            public function reset(): void { throw new \RuntimeException('boom'); }
        };

        $resetter = new ServiceResetter();
        $resetter->add('my.service', $service);

        $resetter->reset(2);

        $this->expectException(ServiceResetException::class);

        $resetter->reset(2);
    }

    public function test_service_reset_exception_preserves_the_original_exception_as_previous(): void
    {
        $original = new \RuntimeException('boom');

        $service = new class($original) implements ResettableInterface {
            public function __construct(private \Throwable $exception) {}
            public function reset(): void { throw $this->exception; }
        };

        $resetter = new ServiceResetter();
        $resetter->add('my.service', $service);

        try {
            $resetter->reset(1);
            $this->fail('Expected ServiceResetException was not thrown.');
        } catch (ServiceResetException $e) {
            $this->assertSame($original, $e->getPrevious());
        }
    }

    public function test_reset_clears_a_service_failure_count_after_a_subsequent_successful_reset(): void
    {
        $shouldFail = true;

        $service = new class($shouldFail) implements ResettableInterface {
            public function __construct(private bool &$shouldFail) {}
            public function reset(): void
            {
                if ($this->shouldFail) {
                    throw new \RuntimeException('boom');
                }
            }
        };

        $resetter = new ServiceResetter();
        $resetter->add('my.service', $service);

        $resetter->reset(2);

        $shouldFail = false;
        $resetter->reset(2);

        $this->assertSame([], $resetter->getDebugInfo()['failures']);

        $shouldFail = true;
        $resetter->reset(2);

        $this->expectException(ServiceResetException::class);

        $resetter->reset(2);
    }

    // -------------------------------------------------------------------------
    // failure threshold — ThresholdAwareResettableInterface
    // -------------------------------------------------------------------------

    public function test_reset_uses_the_service_own_threshold_instead_of_the_callsite_default(): void
    {
        $service = new class implements ThresholdAwareResettableInterface {
            public function reset(): void { throw new \RuntimeException('boom'); }
            public function getFailureThreshold(): int { return 1; }
        };

        $resetter = new ServiceResetter();
        $resetter->add('my.service', $service);

        $this->expectException(ServiceResetException::class);

        // Callsite default (3) would tolerate this failure; the service's own
        // threshold of 1 should trip on the very first failure instead.
        $resetter->reset();
    }

    public function test_reset_does_not_throw_while_a_threshold_aware_service_stays_below_its_own_threshold(): void
    {
        $service = new class implements ThresholdAwareResettableInterface {
            public function reset(): void { throw new \RuntimeException('boom'); }
            public function getFailureThreshold(): int { return 5; }
        };

        $resetter = new ServiceResetter();
        $resetter->add('my.service', $service);

        // Callsite default (3) would trip here; the service's own threshold
        // of 5 should not.
        $resetter->reset(1);

        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // failure tracking key — id, not class
    // -------------------------------------------------------------------------

    public function test_failure_tracking_is_isolated_per_id_even_when_the_same_class_backs_multiple_ids(): void
    {
        $healthy = new ToggleableResettable();

        $failing = new ToggleableResettable();
        $failing->shouldFail = true;

        $resetter = new ServiceResetter();
        // Added in this order so, under reset()'s plain registration-order
        // iteration, the failing instance resets first and the healthy
        // instance (same class, different id) resets second; if failure
        // tracking were still keyed by class instead of id, the healthy
        // instance's successful reset would clear the failing instance's
        // failure count.
        $resetter->add('service.failing', $failing);
        $resetter->add('service.healthy', $healthy);

        $resetter->reset(3);

        $this->assertSame(['service.failing' => 1], $resetter->getDebugInfo()['failures']);
    }
}

final class ToggleableResettable implements ResettableInterface
{
    public bool $shouldFail = false;

    public function reset(): void
    {
        if ($this->shouldFail) {
            throw new \RuntimeException('boom');
        }
    }
}
