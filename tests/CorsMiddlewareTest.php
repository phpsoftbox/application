<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Tests;

use PhpSoftBox\Application\Middleware\CorsMiddleware;
use PhpSoftBox\Http\Message\Response;
use PhpSoftBox\Http\Message\ResponseFactory;
use PhpSoftBox\Http\Message\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CorsMiddlewareTest extends TestCase
{
    /**
     * Проверяем preflight-ответ и CORS заголовки.
     */
    public function testPreflightAddsCorsHeaders(): void
    {
        $middleware = new CorsMiddleware(
            responseFactory: new ResponseFactory(),
            allowedOrigins: ['https://example.com'],
            allowCredentials: true,
            maxAge: 600,
        );

        $request = new ServerRequest('OPTIONS', 'https://api.example.com/')
            ->withHeader('Origin', 'https://example.com')
            ->withHeader('Access-Control-Request-Method', 'POST');

        $handler = new class () implements RequestHandlerInterface {
            public function handle(
                ServerRequestInterface $request,
            ): ResponseInterface {
                return new Response(200);
            }
        };

        $response = $middleware->process($request, $handler);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('https://example.com', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertSame('true', $response->getHeaderLine('Access-Control-Allow-Credentials'));
        $this->assertSame('600', $response->getHeaderLine('Access-Control-Max-Age'));
        $this->assertNotSame('', $response->getHeaderLine('Access-Control-Allow-Methods'));
    }

    /**
     * Проверяем, что при запрещенном origin заголовки не добавляются.
     */
    public function testDisallowedOriginSkipsHeaders(): void
    {
        $middleware = new CorsMiddleware(
            responseFactory: new ResponseFactory(),
            allowedOrigins: ['https://example.com'],
        );

        $request = new ServerRequest('GET', 'https://api.example.com/')
            ->withHeader('Origin', 'https://evil.example.com');

        $handler = new class () implements RequestHandlerInterface {
            public function handle(
                ServerRequestInterface $request,
            ): ResponseInterface {
                return new Response(200);
            }
        };

        $response = $middleware->process($request, $handler);

        $this->assertSame('', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }
}
