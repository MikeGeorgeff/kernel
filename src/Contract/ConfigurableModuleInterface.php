<?php

namespace Georgeff\Kernel\Contract;

/**
 * Adds environment-driven configuration to ModuleInterface: implement when a module needs
 * to contribute config values, e.g. from Env::get().
 */
interface ConfigurableModuleInterface extends ModuleInterface
{
    /**
     * Called during the moduleLoad phase, before register(). Merged across every module's
     * config() into Config\ConfigInterface; a later module silently wins on key collision.
     *
     * @return array<string, mixed>
     */
    public function config(EnvironmentInterface $env): array;
}
