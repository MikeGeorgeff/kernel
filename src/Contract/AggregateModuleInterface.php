<?php

namespace Georgeff\Kernel\Contract;

interface AggregateModuleInterface extends ModuleInterface
{
    /**
     * @return ModuleInterface[]
     */
    public function modules(EnvironmentInterface $env): array;
}
