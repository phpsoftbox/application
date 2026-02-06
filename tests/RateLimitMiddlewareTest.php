<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Tests;

use PhpSoftBox\Application\Exception\TooManyRequestsHttpException;
use PhpSoftBox\Application\Middleware\RateLimitMiddleware;
use PhpSoftBox\Application\Tests\Fixtures\ArrayCache;
use PhpSoftBox\Http\Message\Response;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\RateLimiter\RateLimiterInterface;
use PhpSoftBox\RateLimiter\RateLimitResult;
use PhpSoftBox\RateLimiter\SimpleCacheRateLimiter;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RateLimitMiddlewareTest extends TestCase
{
    /**
     * Проверяем, что после превышения лимита бросается исключение.
     */
    public function testRateLimitExceeded(): void
    {
        $cache = new ArrayCache();

        $limiter = new SimpleCacheRateLimiter($cache);

        $middleware = new RateLimitMiddleware($limiter, maxAttempts: 1, decaySeconds: 60);

        $request = new ServerRequest('GET', 'https://example.com/');

        $handler = new class () implements RequestHandlerInterface {
            public function handle(
                ServerRequestInterface $request,
            ): ResponseInterface {
                return new Response(200);
            }
        };

        $middleware->process($request, $handler);

        try {
            $middleware->process($request, $handler);
            self::fail('The second request must exceed the configured rate limit.');
        } catch (TooManyRequestsHttpException $exception) {
            self::assertSame('1', $exception->headers()['X-RateLimit-Limit'] ?? null);
            self::assertSame('0', $exception->headers()['X-RateLimit-Remaining'] ?? null);
            self::assertArrayHasKey('X-RateLimit-Reset', $exception->headers());
            self::assertArrayHasKey('Retry-After', $exception->headers());
        }
    }

    public function testNormalizesKeyAndUsesAbsoluteResetHeader(): void
    {
        $capturedKey = null;
        $limiter     = new class ($capturedKey) implements RateLimiterInterface {
            public ?string $capturedKey = null;

            public function __construct(?string &$capturedKey)
            {
                $this->capturedKey = & $capturedKey;
            }

            public function hit(string $key, int $maxAttempts, int $decaySeconds): RateLimitResult
            {
                $this->capturedKey = $key;

                return new RateLimitResult(true, $maxAttempts, 4, 30, 1_234_567);
            }
        };
        $middleware = new RateLimitMiddleware($limiter, namespace: 'node_api');
        $request    = new ServerRequest(
            'GET',
            'https://example.com/api/node/v1',
            serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
        );

        $handler = new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response();
            }
        };

        $response = $middleware->process($request, $handler);

        self::assertMatchesRegularExpression('/^node_api\.[a-f0-9]{64}$/D', (string) $capturedKey);
        self::assertSame('1234567', $response->getHeaderLine('X-RateLimit-Reset'));
        self::assertSame('4', $response->getHeaderLine('X-RateLimit-Remaining'));
    }
}
