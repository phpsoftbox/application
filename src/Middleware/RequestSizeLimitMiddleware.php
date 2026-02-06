<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Middleware;

use PhpSoftBox\Application\Exception\PayloadTooLargeHttpException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RequestSizeLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly int $maxBytes,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $length = (int) $request->getHeaderLine('Content-Length');

        if ($length > 0 && $length > $this->maxBytes) {
            throw new PayloadTooLargeHttpException('Payload Too Large');
        }

        return $handler->handle($request);
    }
}
