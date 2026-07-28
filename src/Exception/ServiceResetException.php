<?php

namespace Georgeff\Kernel\Exception;

use Throwable;

final class ServiceResetException extends \RuntimeException implements KernelExceptionInterface
{
    use ThrowHelpers;

    /**
     * @param array<string, Throwable> $failures
     */
    public static function failMany(array $failures): never
    {
        $count = count($failures);

        self::throw(
            "[$count] services reached their reset failure threshold: " . implode('; ', array_map(
                static fn(string $id, Throwable $e) => "[$id]: {$e->getMessage()}",
                array_keys($failures),
                $failures
            )),
            array_values($failures)[0]
        );
    }
}
