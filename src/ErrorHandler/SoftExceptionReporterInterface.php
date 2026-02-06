<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\ErrorHandler;

use Throwable;

interface SoftExceptionReporterInterface
{
    public function report(Throwable $exception, ?ExceptionReportContext $context = null): void;

    public function reportIf(
        bool $condition,
        Throwable $exception,
        ?ExceptionReportContext $context = null,
    ): void;
}
