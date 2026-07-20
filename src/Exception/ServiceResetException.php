<?php

namespace Georgeff\Kernel\Exception;

use Throwable;

final class ServiceResetException extends \RuntimeException implements KernelExceptionInterface
{
    public static function fail(string $service, Throwable $exception): never
    {
        throw new self(
            "Reset failure threshold for service [$service] exceeded",
            previous: $exception
        );
    }
}
