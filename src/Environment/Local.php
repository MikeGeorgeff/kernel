<?php

namespace Georgeff\Kernel\Environment;

final class Local extends AbstractEnvironment
{
    public function getValue(): string
    {
        return 'local';
    }
}
