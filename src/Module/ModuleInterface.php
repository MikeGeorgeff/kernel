<?php

namespace Georgeff\Kernel\Module;

use Georgeff\Kernel\KernelInterface;

interface ModuleInterface
{
    /**
     * Register modules service definitions
     */
    public function register(KernelInterface $kernel): void;
}
