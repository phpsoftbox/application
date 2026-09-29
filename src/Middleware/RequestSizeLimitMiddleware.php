<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Middleware;

use PhpSoftBox\Application\Exception\PayloadTooLargeHttpException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function strlen;

final class RequestSizeLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly int $maxBytes,
    ) {
    }

    /**
     * Лимит по `Content-Length`, а без него (chunked) — по фактическому размеру тела: заголовок можно не присылать.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $length = $request->getHeaderLine('Content-Length');

        if ($length !== '') {
            if ((int) $length > $this->maxBytes) {
                throw new PayloadTooLargeHttpException('Payload Too Large');
            }

            return $handler->handle($request);
        }

        if ($this->bodySize($request->getBody()) > $this->maxBytes) {
            throw new PayloadTooLargeHttpException('Payload Too Large');
        }

        return $handler->handle($request);
    }

    /**
     * Размер тела: из потока, если известен, иначе чтением не дальше лимита + 1 байт.
     */
    private function bodySize(StreamInterface $body): int
    {
        $size = $body->getSize();
        if ($size !== null) {
            return $size;
        }

        if (!$body->isReadable()) {
            return 0;
        }

        $read = 0;
        while (!$body->eof() && $read <= $this->maxBytes) {
            $read += strlen($body->read(8192));
        }

        if ($body->isSeekable()) {
            $body->rewind();
        }

        return $read;
    }
}
