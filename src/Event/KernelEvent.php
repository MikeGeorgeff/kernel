<?php

namespace Georgeff\Kernel\Event;

use Georgeff\Kernel\KernelInterface;

class KernelEvent
{
    public function __construct(public readonly KernelInterface $kernel) {}
}
