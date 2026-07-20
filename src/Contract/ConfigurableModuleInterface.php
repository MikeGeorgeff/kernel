<?php

namespace Georgeff\Kernel\Contract;

use Georgeff\Kernel\Environment;

interface ConfigurableModuleInterface extends ModuleInterface
{
    /**
     * Get module configuration
     *
     * @return array<string, mixed>
     */
    public function config(Environment $env): array;
}
