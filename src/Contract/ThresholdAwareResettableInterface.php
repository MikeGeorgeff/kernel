<?php

namespace Georgeff\Kernel\Contract;

interface ThresholdAwareResettableInterface extends ResettableInterface
{
    public function getFailureThreshold(): int;
}
