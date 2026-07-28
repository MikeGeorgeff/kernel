<?php

namespace Georgeff\Kernel\Exception;

use Throwable;

final class ModuleException extends \RuntimeException implements KernelExceptionInterface
{
    use ThrowHelpers;

    public static function throwOnRegistrationError(string $module, Throwable $exception): never
    {
        self::throw(
            "Failed to register module [$module]: {$exception->getMessage()}",
            $exception
        );
    }

    public static function throwOnBootError(string $module, Throwable $exception): never
    {
        self::throw(
            "Failed to boot module [$module]: {$exception->getMessage()}",
            $exception
        );
    }
}
