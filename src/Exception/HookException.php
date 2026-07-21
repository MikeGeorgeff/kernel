<?php

namespace Georgeff\Kernel\Exception;

use Throwable;

final class HookException extends \RuntimeException implements KernelExceptionInterface
{
    use ThrowHelpers;

    public static function throwOnCallbackError(string $hook, Throwable $exception): never
    {
        self::throw(
            "Hook callback for [$hook] failed: {$exception->getMessage()}",
            $exception
        );
    }

    /**
     * @param Throwable[] $exceptions
     */
    public static function throwOnCallbackErrors(string $hook, array $exceptions): never
    {
        $count = count($exceptions);

        self::throw(
            "[$count] callbacks for [$hook] failed: " . implode('; ', array_map(
                static fn(Throwable $e) => $e->getMessage(),
                $exceptions
            )),
            $exceptions[0]
        );
    }
}
