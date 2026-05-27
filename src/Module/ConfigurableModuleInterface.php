<?php

namespace Georgeff\Kernel\Module;

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
