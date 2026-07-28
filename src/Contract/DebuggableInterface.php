<?php

namespace Georgeff\Kernel\Contract;

interface DebuggableInterface
{
    /**
     * Services that implement this interface will have their debug info collected automatically when the kernel is in debug mode
     *
     * The returned array must be safe to print or serialize as-is: scalars (`string`, `int`, `float`, `bool`),
     * `null`, and nested arrays of the same, keyed however is useful. Do not put objects or callables in as
     * values. If a value is itself `DebuggableInterface`, unwrap it explicitly by calling its own
     * `getDebugInfo()` rather than embedding the instance — the instance itself is not printable. Anything
     * that slips through this contract still gets sanitized defensively by the debug dumper (objects reduced
     * to their class name, callables to a reference id), but that's a safety net for the dumper, not a
     * substitute for returning meaningful data here.
     *
     * @return array<array-key, mixed>
     */
    public function getDebugInfo(): array;
}
