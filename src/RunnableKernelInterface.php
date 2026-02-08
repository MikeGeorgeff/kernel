<?php

namespace Georgeff\Kernel;

interface RunnableKernelInterface extends KernelInterface
{
    public function run(): int;
}
