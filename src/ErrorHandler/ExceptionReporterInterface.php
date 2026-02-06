<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\ErrorHandler;

use Throwable;

interface ExceptionReporterInterface
{
    public function report(Throwable $exception, ?ExceptionReportContext $context = null): void;
}
