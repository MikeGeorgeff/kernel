<?php

namespace Georgeff\Kernel\Contract;

/**
 * Lets a shared, long-lived service clear its own internal state on demand, auto-detected
 * on resolution (no manual tagging required) and invoked via Kernel::resetShared(), for
 * long-running processes (workers/daemons) wanting a clean slate between units of work.
 */
interface ResettableInterface
{
    /**
     * Restore internal state to how it was right after construction. Takes no parameters:
     * every collaborator was already supplied via the constructor.
     */
    public function reset(): void;
}
