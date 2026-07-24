<?php

namespace Georgeff\Kernel\Contract;

use Georgeff\Kernel\KernelInterface;

/**
 * Base extension point for kernel modules: implement this (or a more specific module
 * interface below) and add it via Kernel::addModule().
 */
interface ModuleInterface
{
    /**
     * Called during the moduleRegistration boot phase, with full kernel access.
     */
    public function register(KernelInterface $kernel): void;
}
