<?php

namespace Georgeff\Kernel\Exception;

use RuntimeException;

final class KernelException extends RuntimeException
{
    public static function throw(string $message): never
    {
        throw new self($message);
    }

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
}
