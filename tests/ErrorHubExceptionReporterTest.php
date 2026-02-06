<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Tests;

use PhpSoftBox\Application\ErrorHandler\ErrorHubExceptionInterface;
use PhpSoftBox\Application\ErrorHandler\ErrorHubExceptionReporter;
use PhpSoftBox\Application\ErrorHandler\ErrorHubExceptionTrait;
use PhpSoftBox\Application\ErrorHandler\ExceptionReportContext;
use PhpSoftBox\Http\Message\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

#[CoversClass(ErrorHubExceptionReporter::class)]
#[CoversMethod(ErrorHubExceptionReporter::class, 'report')]
#[CoversMethod(ErrorHubExceptionReporter::class, 'buildPayload')]
final class ErrorHubExceptionReporterTest extends TestCase
{
    /**
     * Проверяем, что репортинг пропускается без baseUrl.
     */
    #[Test]
    public function testSkipsWhenEndpointIsEmpty(): void
    {
        $reporter = new ErrorHubExceptionReporter(baseUrl: '', projectKey: 'key');

        $this->expectNotToPerformAssertions();
        $reporter->report(
            new RuntimeException('boom'),
            ExceptionReportContext::fromRequest(new ServerRequest('GET', 'https://example.com/')),
        );
    }

    /**
     * Проверяем, что payload собирается с базовыми полями.
     */
    #[Test]
    public function testBuildsPayloadAndSendsRequest(): void
    {
        $reporter = new ErrorHubExceptionReporter(
            baseUrl: 'http://localhost',
            projectKey: 'project',
            tags: ['env:test'],
            timezone: 'UTC',
        );

        $request = new ServerRequest('POST', 'https://example.com/foo')
            ->withHeader('X-Request-Id', 'req-123');

        $reflection = new ReflectionClass($reporter);

        $method  = $reflection->getMethod('buildPayload');
        $payload = $method->invoke(
            $reporter,
            new RuntimeException('boom'),
            ExceptionReportContext::fromRequest($request),
        );

        self::assertSame('boom', $payload['message']);
        self::assertSame('error', $payload['level']);
        self::assertSame('env:test', $payload['tags'][0]);
        self::assertSame('req-123', $payload['context']['request_id']);
        self::assertSame('POST', $payload['context']['method']);
        self::assertSame('https://example.com/foo', $payload['context']['uri']);
        self::assertSame(RuntimeException::class, $payload['exception']['class']);
        self::assertSame('boom', $payload['exception']['message']);
        self::assertIsArray($payload['exception']['stacktrace']);
        self::assertNotEmpty($payload['exception']['stacktrace']);
    }

    /**
     * Проверяем, что управляющие символы удаляются из payload.
     */
    #[Test]
    public function testSanitizesPayloadControlChars(): void
    {
        $reporter = new ErrorHubExceptionReporter(
            baseUrl: 'http://localhost',
            projectKey: 'project',
            context: ['note' => "bad\0value"],
        );

        $request    = new ServerRequest('GET', 'https://example.com/');
        $reflection = new ReflectionClass($reporter);

        $method  = $reflection->getMethod('buildPayload');
        $payload = $method->invoke(
            $reporter,
            new RuntimeException("boom\0"),
            ExceptionReportContext::fromRequest($request),
        );

        self::assertSame('badvalue', $payload['context']['note']);
        self::assertSame('boom', $payload['message']);
        self::assertSame('boom', $payload['exception']['message']);
    }

    /**
     * Проверяем, что теги из исключения добавляются к тегам репортера.
     */
    #[Test]
    public function testMergesExceptionTags(): void
    {
        $reporter = new ErrorHubExceptionReporter(
            baseUrl: 'http://localhost',
            projectKey: 'project',
            tags: ['env:test'],
        );

        $exception = new class ('boom') extends RuntimeException implements ErrorHubExceptionInterface {
            use ErrorHubExceptionTrait;
        };
        $exception->setErrorHubTags(['module:auth']);

        $request    = new ServerRequest('GET', 'https://example.com/');
        $reflection = new ReflectionClass($reporter);

        $method  = $reflection->getMethod('buildPayload');
        $payload = $method->invoke($reporter, $exception, ExceptionReportContext::fromRequest($request));

        self::assertContains('env:test', $payload['tags']);
        self::assertContains('module:auth', $payload['tags']);
    }

    /**
     * Проверяем, что контекст из исключения дополняет общий контекст.
     */
    #[Test]
    public function testMergesExceptionContext(): void
    {
        $reporter = new ErrorHubExceptionReporter(
            baseUrl: 'http://localhost',
            projectKey: 'project',
            context: ['service' => 'admin'],
        );

        $exception = new class ('boom') extends RuntimeException implements ErrorHubExceptionInterface {
            use ErrorHubExceptionTrait;
        };
        $exception->setErrorHubContext(['module' => 'billing']);

        $request    = new ServerRequest('GET', 'https://example.com/');
        $reflection = new ReflectionClass($reporter);

        $method  = $reflection->getMethod('buildPayload');
        $payload = $method->invoke($reporter, $exception, ExceptionReportContext::fromRequest($request));

        self::assertSame('admin', $payload['context']['service']);
        self::assertSame('billing', $payload['context']['module']);
    }

    /**
     * Проверяем fallback timezone при неверном значении.
     */
    #[Test]
    public function testTimezoneFallback(): void
    {
        $reporter = new ErrorHubExceptionReporter(
            baseUrl: 'http://localhost',
            projectKey: 'project',
            timezone: 'Invalid/Zone',
        );

        $reflection = new ReflectionClass($reporter);

        $method  = $reflection->getMethod('buildPayload');
        $payload = $method->invoke(
            $reporter,
            new RuntimeException('boom'),
            ExceptionReportContext::fromRequest(new ServerRequest('GET', 'https://example.com/')),
        );

        self::assertIsArray($payload);
        self::assertArrayHasKey('timestamp', $payload);
    }

    #[Test]
    public function testBuildsPayloadWithoutHttpRequest(): void
    {
        $reporter = new ErrorHubExceptionReporter(
            baseUrl: 'http://localhost',
            projectKey: 'project',
        );

        $reflection = new ReflectionClass($reporter);

        $method  = $reflection->getMethod('buildPayload');
        $payload = $method->invoke(
            $reporter,
            new RuntimeException('schema changed'),
            new ExceptionReportContext(
                data: ['integration' => 'marketplace'],
                tags: ['soft-exception'],
            ),
        );

        self::assertSame('marketplace', $payload['context']['integration']);
        self::assertArrayNotHasKey('method', $payload['context']);
        self::assertContains('soft-exception', $payload['tags']);
    }
}
