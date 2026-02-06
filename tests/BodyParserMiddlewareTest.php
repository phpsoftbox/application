<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Tests;

use PhpSoftBox\Application\Exception\BadRequestHttpException;
use PhpSoftBox\Application\Middleware\BodyParserMiddleware;
use PhpSoftBox\Http\Message\Response;
use PhpSoftBox\Http\Message\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class BodyParserMiddlewareTest extends TestCase
{
    /**
     * Проверяем разбор JSON тела запроса.
     */
    public function testParsesJsonBody(): void
    {
        $middleware = new BodyParserMiddleware();
        $request    = new ServerRequest(
            'POST',
            'https://example.com/',
            ['Content-Type' => 'application/json'],
            '{"name":"Arthur"}',
        );

        $parsed = null;

        $handler = new class ($parsed) implements RequestHandlerInterface {
            private mixed $parsed;

            public function __construct(mixed &$parsed)
            {
                $this->parsed = &$parsed;
            }

            public function handle(
                ServerRequestInterface $request,
            ): ResponseInterface {
                $this->parsed = $request->getParsedBody();

                return new Response(200);
            }
        };

        $middleware->process($request, $handler);

        $this->assertSame(['name' => 'Arthur'], $parsed);
    }

    /**
     * Проверяем разбор form-urlencoded тела запроса.
     */
    public function testParsesFormBody(): void
    {
        $middleware = new BodyParserMiddleware();
        $request    = new ServerRequest(
            'POST',
            'https://example.com/',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            'name=Arthur&age=10',
        );

        $parsed = null;

        $handler = new class ($parsed) implements RequestHandlerInterface {
            private mixed $parsed;

            public function __construct(mixed &$parsed)
            {
                $this->parsed = &$parsed;
            }

            public function handle(
                ServerRequestInterface $request,
            ): ResponseInterface {
                $this->parsed = $request->getParsedBody();

                return new Response(200);
            }
        };

        $middleware->process($request, $handler);

        $this->assertSame(['name' => 'Arthur', 'age' => '10'], $parsed);
    }

    /**
     * Проверяем, что при невалидном JSON выбрасывается исключение.
     */
    public function testInvalidJsonThrows(): void
    {
        $middleware = new BodyParserMiddleware();
        $request    = new ServerRequest(
            'POST',
            'https://example.com/',
            ['Content-Type' => 'application/json'],
            '{bad',
        );

        $handler = new class () implements RequestHandlerInterface {
            public function handle(
                ServerRequestInterface $request,
            ): ResponseInterface {
                return new Response(200);
            }
        };

        $this->expectException(BadRequestHttpException::class);

        $middleware->process($request, $handler);
    }
}
