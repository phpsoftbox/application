<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\ErrorHandler;

use Throwable;

final readonly class CompositeExceptionReporter implements ExceptionReporterInterface
{
    /**
     * @param list<ExceptionReporterInterface> $reporters
     */
    public function __construct(
        private array $reporters,
    ) {
    }

    public function report(Throwable $exception, ?ExceptionReportContext $context = null): void
    {
        foreach ($this->reporters as $reporter) {
            try {
                $reporter->report($exception, $context);
            } catch (Throwable) {
                // Reporting must not replace the original application outcome.
            }
        }
    }
}
