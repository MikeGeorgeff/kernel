<?php

namespace Georgeff\Kernel\Exception;

use Throwable;

final class ConfigException extends \RuntimeException implements KernelExceptionInterface
{
    use ThrowHelpers;
}
