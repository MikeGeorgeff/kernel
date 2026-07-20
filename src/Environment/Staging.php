<?php

namespace Georgeff\Kernel\Environment;

final class Staging extends AbstractEnvironment
{
    public function getValue(): string
    {
        return 'staging';
    }
}
