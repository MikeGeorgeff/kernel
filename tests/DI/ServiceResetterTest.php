<?php

namespace Georgeff\Kernel\Test\DI;

use Georgeff\Kernel\Contract\ResettableInterface;
use Georgeff\Kernel\DI\ServiceResetter;
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
}
