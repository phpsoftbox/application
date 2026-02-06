<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\ErrorHandler;

enum SoftExceptionMode: string
{
    case Report = 'report';
    case Throw  = 'throw';
}
