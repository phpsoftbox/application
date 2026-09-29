<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Tests;

use PhpSoftBox\Application\Exception\PayloadTooLargeHttpException;
use PhpSoftBox\Application\Middleware\RequestSizeLimitMiddleware;
use PhpSoftBox\Http\Message\Response;
use PhpSoftBox\Http\Message\ServerRequest;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function str_repeat;
use function strlen;
use function substr;

use const SEEK_SET;

final class RequestSizeLimitMiddlewareTest extends TestCase
{
    /**
     * Проверяем, что превышение размера запроса вызывает исключение.
     */
    public function testThrowsWhenContentLengthTooLarge(): void
    {
        $middleware = new RequestSizeLimitMiddleware(100);

        $request = new ServerRequest('POST', 'https://example.com/')
            ->withHeader('Content-Length', '101');

        $handler = new class () implements RequestHandlerInterface {
            public function handle(
                ServerRequestInterface $request,
            ): ResponseInterface {
                return new Response(200);
            }
        };

        $this->expectException(PayloadTooLargeHttpException::class);

        $middleware->process($request, $handler);
    }

    /**
     * Проверяем, что запрос проходит при допустимом размере.
     */
    public function testAllowsWhenContentLengthWithinLimit(): void
    {
        $middleware = new RequestSizeLimitMiddleware(100);

        $request = new ServerRequest('POST', 'https://example.com/')
            ->withHeader('Content-Length', '100');

        $handler = new class () implements RequestHandlerInterface {
            public function handle(
                ServerRequestInterface $request,
            ): ResponseInterface {
                return new Response(200);
            }
        };

        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * Проверим, что без Content-Length (chunked) лимит проверяется по фактическому размеру тела.
     *
     * @see RequestSizeLimitMiddleware::process()
     */
    #[Test]
    public function checksBodyWithoutContentLength(): void
    {
        $stream = new class (str_repeat('x', 150)) implements StreamInterface {
            private int $position = 0;

            public function __construct(
                private readonly string $data,
            ) {
            }

            public function __toString(): string
            {
                return $this->data;
            }

            public function close(): void
            {
            }

            public function detach()
            {
                return null;
            }

            public function getSize(): ?int
            {
                return null;
            }

            public function tell(): int
            {
                return $this->position;
            }

            public function eof(): bool
            {
                return $this->position >= strlen($this->data);
            }

            public function isSeekable(): bool
            {
                return true;
            }

            public function seek(int $offset, int $whence = SEEK_SET): void
            {
                $this->position = $offset;
            }

            public function rewind(): void
            {
                $this->position = 0;
            }

            public function isWritable(): bool
            {
                return false;
            }

            public function write(string $string): int
            {
                return 0;
            }

            public function isReadable(): bool
            {
                return true;
            }

            public function read(int $length): string
            {
                $chunk = substr($this->data, $this->position, $length);
                $this->position += strlen($chunk);

                return $chunk;
            }

            public function getContents(): string
            {
                return $this->read(strlen($this->data));
            }

            public function getMetadata(?string $key = null): mixed
            {
                return null;
            }
        };

        $this->expectException(PayloadTooLargeHttpException::class);

        new RequestSizeLimitMiddleware(100)->process(
            new ServerRequest('POST', 'https://example.com/upload', ['Transfer-Encoding' => 'chunked'], $stream),
            new class () implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return new Response(200);
                }
            },
        );
    }
}
