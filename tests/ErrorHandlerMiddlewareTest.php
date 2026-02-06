<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Tests;

use PhpSoftBox\Application\ErrorHandler\ExceptionHandlerInterface;
use PhpSoftBox\Application\Middleware\ErrorHandlerMiddleware;
use PhpSoftBox\Http\Message\ResponseFactory;
use PhpSoftBox\Http\Message\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Throwable;

final class ErrorHandlerMiddlewareTest extends TestCase
{
    /**
     * Проверяем, что остальные исключения обрабатываются ErrorHandler.
     */
    public function testHandlesOtherExceptions(): void
    {
        $request = new ServerRequest('GET', 'https://example.com/');

        $handler = new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new RuntimeException('Oops');
            }
        };

        $exceptionHandler = new class () implements ExceptionHandlerInterface {
            public bool $called = false;

            public function handle(Throwable $exception, ServerRequestInterface $request): ResponseInterface
            {
                $this->called = true;

                return new ResponseFactory()->createResponse(418);
            }
        };

        $middleware = new ErrorHandlerMiddleware($exceptionHandler);

        $response = $middleware->process($request, $handler);

        $this->assertSame(418, $response->getStatusCode());
        $this->assertTrue($exceptionHandler->called);
    }
}
