<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Tests;

use PhpSoftBox\Application\ErrorHandler\CompositeExceptionReporter;
use PhpSoftBox\Application\ErrorHandler\ExceptionReportContext;
use PhpSoftBox\Application\ErrorHandler\ExceptionReporterInterface;
use PhpSoftBox\Application\ErrorHandler\SoftExceptionMode;
use PhpSoftBox\Application\ErrorHandler\SoftExceptionReporter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

#[CoversClass(SoftExceptionReporter::class)]
#[CoversClass(CompositeExceptionReporter::class)]
final class SoftExceptionReporterTest extends TestCase
{
    #[Test]
    public function reportModeDelegatesAndReturnsControl(): void
    {
        $spy = new SoftExceptionSpyReporter();

        $reporter  = new SoftExceptionReporter($spy, SoftExceptionMode::Report);
        $exception = new RuntimeException('External schema changed.');
        $context   = new ExceptionReportContext(['integration' => 'marketplace']);

        $reporter->report($exception, $context);

        self::assertSame($exception, $spy->exception);
        self::assertSame($context, $spy->context);
    }

    #[Test]
    public function throwModeThrowsOriginalExceptionWithoutReporting(): void
    {
        $spy = new SoftExceptionSpyReporter();

        $reporter  = new SoftExceptionReporter($spy, SoftExceptionMode::Throw);
        $exception = new RuntimeException('External schema changed.');

        try {
            $reporter->report($exception);
            self::fail('The original exception was not thrown.');
        } catch (Throwable $thrown) {
            self::assertSame($exception, $thrown);
        }

        self::assertNull($spy->exception);
    }

    #[Test]
    public function reportIfSkipsFalseCondition(): void
    {
        $spy = new SoftExceptionSpyReporter();

        $reporter = new SoftExceptionReporter($spy);

        $reporter->reportIf(false, new RuntimeException('Not reported.'));

        self::assertNull($spy->exception);
    }

    #[Test]
    public function compositeContinuesWhenOneReporterFails(): void
    {
        $spy = new SoftExceptionSpyReporter();

        $reporter = new CompositeExceptionReporter([
            new class () implements ExceptionReporterInterface {
                public function report(Throwable $exception, ?ExceptionReportContext $context = null): void
                {
                    throw new RuntimeException('Reporter is unavailable.');
                }
            },
            $spy,
        ]);

        $exception = new RuntimeException('Application problem.');

        $reporter->report($exception);

        self::assertSame($exception, $spy->exception);
    }

    #[Test]
    public function reportModeDoesNotFailWhenReporterFails(): void
    {
        $reporter = new SoftExceptionReporter(
            new class () implements ExceptionReporterInterface {
                public function report(Throwable $exception, ?ExceptionReportContext $context = null): void
                {
                    throw new RuntimeException('Reporter is unavailable.');
                }
            },
        );

        $reporter->report(new RuntimeException('Handled application problem.'));

        self::assertTrue(true);
    }
}

final class SoftExceptionSpyReporter implements ExceptionReporterInterface
{
    public ?Throwable $exception            = null;
    public ?ExceptionReportContext $context = null;

    public function report(Throwable $exception, ?ExceptionReportContext $context = null): void
    {
        $this->exception = $exception;
        $this->context   = $context;
    }
}
