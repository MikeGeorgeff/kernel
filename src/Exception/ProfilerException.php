<?php

namespace Georgeff\Kernel\Exception;

final class ProfilerException extends \RuntimeException implements KernelExceptionInterface
{
    use ThrowHelpers;
}
