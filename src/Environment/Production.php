<?php

namespace Georgeff\Kernel\Environment;

final class Production extends AbstractEnvironment
{
    public function getValue(): string
    {
        return 'production';
    }
}
