<?php

namespace Georgeff\Kernel\Exception;

final class KernelException extends \RuntimeException implements KernelExceptionInterface
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
