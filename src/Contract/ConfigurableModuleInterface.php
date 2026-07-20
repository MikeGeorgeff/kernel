<?php

namespace Georgeff\Kernel\Contract;

interface ConfigurableModuleInterface extends ModuleInterface
{
    /**
     * Get module configuration
     *
     * @return array<string, mixed>
     */
    public function config(EnvironmentInterface $env): array;
}
