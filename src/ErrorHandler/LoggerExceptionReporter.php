<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\ErrorHandler;

use Psr\Log\LoggerInterface;
use Throwable;

final readonly class LoggerExceptionReporter implements ExceptionReporterInterface
{
    public function __construct(
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function report(Throwable $exception, ?ExceptionReportContext $reportContext = null): void
    {
        if ($this->logger === null) {
            return;
        }

        $context = $reportContext?->data ?? [];
        $request = $reportContext?->request;
        if ($request !== null) {
            $context['method'] = $request->getMethod();
            $context['uri']    = (string) $request->getUri();
        }
        $context['exception'] = $exception;

        $this->logger->error($exception->getMessage(), $context);
    }
}
