<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\ErrorHandler;

use Throwable;

final readonly class SoftExceptionReporter implements SoftExceptionReporterInterface
{
    public function __construct(
        private ExceptionReporterInterface $reporter,
        private SoftExceptionMode $mode = SoftExceptionMode::Report,
    ) {
    }

    public function report(Throwable $exception, ?ExceptionReportContext $context = null): void
    {
        if ($this->mode === SoftExceptionMode::Throw) {
            throw $exception;
        }

        try {
            $this->reporter->report($exception, $context);
        } catch (Throwable) {
            // A soft exception must not become fatal because its reporter failed.
        }
    }

    public function reportIf(
        bool $condition,
        Throwable $exception,
        ?ExceptionReportContext $context = null,
    ): void {
        if (!$condition) {
            return;
        }

        $this->report($exception, $context);
    }
}
