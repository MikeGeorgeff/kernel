<?php

namespace Georgeff\Kernel\Exception;

use Throwable;

final class ServiceResetException extends \RuntimeException implements KernelExceptionInterface
{
    use ThrowHelpers;

    public static function fail(string $service, Throwable $exception): never
    {
        self::throw(
            "Reset failure threshold for service [$service] exceeded",
            $exception
        );
    }
}
