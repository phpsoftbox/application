<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Tests;

use PhpSoftBox\Application\Middleware\MethodOverrideMiddleware;
use PhpSoftBox\Http\Message\Response;
use PhpSoftBox\Http\Message\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class MethodOverrideMiddlewareTest extends TestCase
{
    /**
     * Проверяем, что метод переопределяется через заголовок.
     */
    public function testOverridesMethodFromHeader(): void
    {
        $middleware = new MethodOverrideMiddleware();

        $request = new ServerRequest('POST', 'https://example.com/')
            ->withHeader('X-HTTP-Method-Override', 'PATCH');

        $captured = null;

        $handler = new class ($captured) implements RequestHandlerInterface {
            private mixed $captured;

            public function __construct(mixed &$captured)
            {
                $this->captured = &$captured;
            }

            public function handle(
                ServerRequestInterface $request,
            ): ResponseInterface {
                $this->captured = $request->getMethod();

                return new Response(200);
            }
        };

        $middleware->process($request, $handler);

        $this->assertSame('PATCH', $captured);
    }

    /**
     * Проверяем, что для не-POST запросов метод не меняется.
     */
    public function testDoesNotOverrideWhenNotPost(): void
    {
        $middleware = new MethodOverrideMiddleware();

        $request = new ServerRequest('GET', 'https://example.com/')
            ->withHeader('X-HTTP-Method-Override', 'DELETE');

        $captured = null;

        $handler = new class ($captured) implements RequestHandlerInterface {
            private mixed $captured;

            public function __construct(mixed &$captured)
            {
                $this->captured = &$captured;
            }

            public function handle(
                ServerRequestInterface $request,
            ): ResponseInterface {
                $this->captured = $request->getMethod();

                return new Response(200);
            }
        };

        $middleware->process($request, $handler);

        $this->assertSame('GET', $captured);
    }
}
