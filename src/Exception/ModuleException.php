<?php

namespace Georgeff\Kernel\Exception;

use Throwable;

final class ModuleException extends \RuntimeException implements KernelExceptionInterface
{
    public static function throwIf(bool $condition, string $message): void
    {
        if ($condition) {
            throw new self($message);
        }
    }

    public static function throwIfNot(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new self($message);
        }
    }

    public static function throwOnRegistrationError(string $module, Throwable $exception): never
    {
        throw new self(
            "Failed to register module [$module]: {$exception->getMessage()}",
            previous: $exception
        );
    }

    public static function throwOnBootError(string $module, Throwable $exception): never
    {
        throw new self(
            "Failed to boot module [$module]: {$exception->getMessage()}",
            previous: $exception
        );
    }
}
