<?php

namespace Georgeff\Kernel\Exception;

use Throwable;

final class DefinitionException extends \RuntimeException implements KernelExceptionInterface
{
    use ThrowHelpers;
}
