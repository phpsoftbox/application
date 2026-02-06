<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\ErrorHandler;

enum ExceptionFormat: string
{
    case JSON = 'json';
    case HTML = 'html';
}
