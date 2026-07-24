<?php

namespace Georgeff\Kernel\Contract;

use Georgeff\Kernel\KernelInterface;

/**
 * Extension point for a kernel that also owns its own run loop (e.g. a CLI application):
 * boot(), run the application, then return an exit code instead of leaving that to the
 * caller.
 */
interface RunnableKernelInterface extends KernelInterface
{
    /**
     * Run the application and return its exit code (0 for success, non-zero for failure).
     */
    public function run(): int;
}
