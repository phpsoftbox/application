<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Tests;

use PhpSoftBox\Application\ErrorHandler\DefaultExceptionHandler;
use PhpSoftBox\Application\ErrorHandler\ExceptionHandlerInterface;
use PhpSoftBox\Application\ErrorHandler\ExceptionReportContext;
use PhpSoftBox\Application\ErrorHandler\ExceptionReporterInterface;
use PhpSoftBox\Application\Exception\NotFoundHttpException;
use PhpSoftBox\Http\Message\ResponseFactory;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Router\Exception\RouteNotFoundException;
use PhpSoftBox\Session\Session;
use PhpSoftBox\Session\SessionStoreInterface;
use PhpSoftBox\Validator\Exception\ValidationException;
use PhpSoftBox\Validator\ValidationError;
use PhpSoftBox\Validator\ValidationResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

#[CoversClass(DefaultExceptionHandler::class)]
final class DefaultExceptionHandlerTest extends TestCase
{
    /**
     * Проверяем, что ValidationException приводит к редиректу и сохранению ошибок в сессии.
     */
    #[Test]
    public function testValidationExceptionRedirectsAndFlashesErrors(): void
    {
        $store = new SpySessionStore();

        $session         = new Session($store);
        $responseFactory = new ResponseFactory();

        $fallback = new class () implements ExceptionHandlerInterface {
            public function handle(Throwable $exception, ServerRequestInterface $request): ResponseInterface
            {
                return new ResponseFactory()->createResponse(500);
            }
        };

        $handler = new DefaultExceptionHandler(
            $fallback,
            $responseFactory,
            $session,
            [],
            [],
            ['password'],
        );

        $request = new ServerRequest(
            'POST',
            'https://example.com/login',
            ['Referer' => '/login', 'X-Inertia' => 'true'],
        )->withParsedBody([
            'login'    => '',
            'password' => 'secret',
        ]);

        $exception = new ValidationException(
            new ValidationResult([
                'login'    => [new ValidationError('login', 'required', 'Login required')],
                'password' => [new ValidationError('password', 'required', 'Password required')],
            ], []),
        );

        $response = $handler->handle($exception, $request);

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/login', $response->getHeaderLine('Location'));
        $this->assertTrue($store->written);

        $this->assertSame(
            ['login' => 'Login required', 'password' => 'Password required'],
            $session->getFlash('errors'),
        );
        $this->assertNull($session->getFlash('danger'));
        $this->assertNull($session->getFlash('error'));
        $this->assertNull($session->getFlash('info'));
        $this->assertSame(['login' => ''], $session->getFlash('old'));
    }

    /**
     * Проверяем, что исключения из списка не репортятся.
     */
    #[Test]
    public function testValidationExceptionDoesNotReportWhenListed(): void
    {
        $store = new SpySessionStore();

        $session         = new Session($store);
        $responseFactory = new ResponseFactory();
        $reporter        = new SpyReporter();

        $handler = new DefaultExceptionHandler(
            new class () implements ExceptionHandlerInterface {
                public function handle(Throwable $exception, ServerRequestInterface $request): ResponseInterface
                {
                    return new ResponseFactory()->createResponse(500);
                }
            },
            $responseFactory,
            $session,
            [$reporter],
            [ValidationException::class],
            [],
        );

        $request   = new ServerRequest('POST', 'https://example.com/login', ['X-Inertia' => 'true']);
        $exception = new ValidationException(new ValidationResult([], []));

        $handler->handle($exception, $request);

        $this->assertFalse($reporter->called);
    }

    /**
     * Проверяем, что JSON-запросы делегируются в fallback-обработчик.
     */
    #[Test]
    public function testValidationExceptionReturnsFallbackForJsonRequests(): void
    {
        $store = new SpySessionStore();

        $session         = new Session($store);
        $responseFactory = new ResponseFactory();

        $fallback = new class () implements ExceptionHandlerInterface {
            public bool $called = false;

            public function handle(Throwable $exception, ServerRequestInterface $request): ResponseInterface
            {
                $this->called = true;

                return new ResponseFactory()->createResponse(422);
            }
        };

        $handler = new DefaultExceptionHandler(
            $fallback,
            $responseFactory,
            $session,
            [],
            [],
            [],
        );

        $request   = new ServerRequest('POST', 'https://example.com/login', ['Accept' => 'application/json']);
        $exception = new ValidationException(new ValidationResult([], []));

        $response = $handler->handle($exception, $request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertTrue($fallback->called);
        $this->assertFalse($store->written);
    }

    /**
     * Проверяем, что роутерные исключения централизованно маппятся до делегирования в fallback.
     */
    #[Test]
    public function testRouteExceptionsAreMappedBeforeFallback(): void
    {
        $store = new SpySessionStore();

        $session         = new Session($store);
        $responseFactory = new ResponseFactory();

        $fallback = new class () implements ExceptionHandlerInterface {
            public ?Throwable $receivedException = null;

            public function handle(Throwable $exception, ServerRequestInterface $request): ResponseInterface
            {
                $this->receivedException = $exception;

                return new ResponseFactory()->createResponse(404);
            }
        };

        $handler = new DefaultExceptionHandler(
            $fallback,
            $responseFactory,
            $session,
        );

        $request = new ServerRequest('GET', 'https://example.com/missing');

        $response = $handler->handle(new RouteNotFoundException('Route with name foo not found'), $request);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertInstanceOf(NotFoundHttpException::class, $fallback->receivedException);
        $this->assertSame('Not Found', $fallback->receivedException?->message());
        $this->assertSame('Route with name foo not found', $fallback->receivedException?->debugMessage());
    }
}

final class SpySessionStore implements SessionStoreInterface
{
    public bool $started = false;
    public bool $written = false;

    /** @var array<string, mixed> */
    private array $data = [];

    public function start(): void
    {
        $this->started = true;
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    public function read(): array
    {
        return $this->data;
    }

    public function write(array $data): void
    {
        $this->written = true;
        $this->data    = $data;
    }

    public function regenerateId(bool $deleteOldSession = true): void
    {
    }

    public function destroy(): void
    {
        $this->data    = [];
        $this->started = false;
    }
}

final class SpyReporter implements ExceptionReporterInterface
{
    public bool $called                     = false;
    public ?ExceptionReportContext $context = null;

    public function report(Throwable $exception, ?ExceptionReportContext $context = null): void
    {
        $this->called  = true;
        $this->context = $context;
    }
}
