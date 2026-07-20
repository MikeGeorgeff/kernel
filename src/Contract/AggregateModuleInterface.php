<?php

namespace Georgeff\Kernel\Contract;

use Georgeff\Kernel\Environment;

interface AggregateModuleInterface extends ModuleInterface
{
    /**
     * @return ModuleInterface[]
     */
    public function modules(Environment $env): array;
}
