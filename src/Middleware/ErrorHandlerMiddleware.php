<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Middleware;

use PhpSoftBox\Application\ErrorHandler\ExceptionHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

final class ErrorHandlerMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ExceptionHandlerInterface $handler,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $exception) {
            return $this->handler->handle($exception, $request);
        }
    }
}
