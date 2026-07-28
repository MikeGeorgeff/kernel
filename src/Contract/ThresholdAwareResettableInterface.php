<?php

namespace Georgeff\Kernel\Contract;

/**
 * Lets an individual ResettableInterface service override resetShared()'s callsite failure
 * threshold: a full override, not additive to it.
 */
interface ThresholdAwareResettableInterface extends ResettableInterface
{
    /**
     * How many consecutive reset() failures to tolerate before ServiceResetException is
     * thrown for this service, instead of the callsite default.
     */
    public function getFailureThreshold(): int;
}
