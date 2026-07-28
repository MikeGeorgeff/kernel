<?php

namespace Georgeff\Kernel\Environment;

final class Testing extends AbstractEnvironment
{
    public function getValue(): string
    {
        return 'testing';
    }
}
