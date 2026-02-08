<?php

namespace Georgeff\Kernel;

enum Environment: string
{
    case Production  = 'production';
    case Staging     = 'staging';
    case Development = 'development';
    case Testing     = 'testing';
}
