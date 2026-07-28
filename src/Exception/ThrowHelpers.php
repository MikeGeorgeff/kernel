<?php

namespace Georgeff\Kernel\Exception;

use Throwable;

trait ThrowHelpers
{
    public static function instance(string $message, ?Throwable $previous = null): static
    {
        return new self($message, previous: $previous);
    }

    public static function throw(string $message, ?Throwable $previous = null): never
    {
        throw self::instance($message, $previous);
    }

    public static function throwIf(bool $condition, string $message, ?Throwable $previous = null): void
    {
        if ($condition) {
            self::throw($message, $previous);
        }
    }

    public static function throwIfNot(bool $condition, string $message, ?Throwable $previous = null): void
    {
        if (!$condition) {
            self::throw($message, $previous);
        }
    }
}
