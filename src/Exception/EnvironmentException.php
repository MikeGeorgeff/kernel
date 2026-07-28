<?php

namespace Georgeff\Kernel\Exception;

use Throwable;

final class EnvironmentException extends \RuntimeException implements KernelExceptionInterface
{
    use ThrowHelpers;
}
