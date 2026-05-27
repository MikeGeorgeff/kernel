<?php

namespace Georgeff\Kernel\Module;

use Georgeff\Kernel\Environment;

interface ModuleRepositoryInterface
{
    /**
     * Add multiple modules to the kernel
     *
     * @return ModuleInterface[]
     */
    public function modules(Environment $env): array;
}
