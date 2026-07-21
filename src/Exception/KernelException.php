<?php

namespace Georgeff\Kernel\Exception;

use Throwable;

final class KernelException extends \RuntimeException implements KernelExceptionInterface
{
    use ThrowHelpers;
}
