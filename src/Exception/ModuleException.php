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

    public static function throwOnRegistrationError(Throwable $exception): never
    {
        throw new self(
            'Module registration error: ' . $exception->getMessage(),
            previous: $exception
        );
    }
}
