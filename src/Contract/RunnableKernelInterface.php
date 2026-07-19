<?php

namespace Georgeff\Kernel\Contract;

use Georgeff\Kernel\KernelInterface;

interface RunnableKernelInterface extends KernelInterface
{
    public function run(): int;
}
