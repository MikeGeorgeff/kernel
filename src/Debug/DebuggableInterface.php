<?php

namespace Georgeff\Kernel\Debug;

interface DebuggableInterface
{
    /**
     * Get debug info
     *
     * Services that implement this interface will have their debug info collected automatically when the kernel is in debug mode
     *
     * @return array<mixed>
     */
    public function getDebugInfo(): array;
}
