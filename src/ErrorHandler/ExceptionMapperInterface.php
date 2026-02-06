<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\ErrorHandler;

use Throwable;

interface ExceptionMapperInterface
{
    public function map(Throwable $exception): ?Throwable;
}
