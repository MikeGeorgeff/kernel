<?php

namespace Georgeff\Kernel\Environment;

final class Development extends AbstractEnvironment
{
    public function getValue(): string
    {
        return 'development';
    }
}
