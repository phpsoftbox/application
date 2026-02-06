<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Middleware;

use Closure;
use InvalidArgumentException;
use PhpSoftBox\Application\Exception\TooManyRequestsHttpException;
use PhpSoftBox\RateLimiter\HashRateLimitKeyNormalizer;
use PhpSoftBox\RateLimiter\RateLimiterInterface;
use PhpSoftBox\RateLimiter\RateLimitKeyNormalizerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function sprintf;

final class RateLimitMiddleware implements MiddlewareInterface
{
    private readonly ?Closure $keyResolver;
    private readonly RateLimitKeyNormalizerInterface $keyNormalizer;

    /**
     * @param callable(ServerRequestInterface): string|null $keyResolver
     */
    public function __construct(
        private readonly RateLimiterInterface $limiter,
        private readonly int $maxAttempts = 60,
        private readonly int $decaySeconds = 60,
        ?callable $keyResolver = null,
        ?RateLimitKeyNormalizerInterface $keyNormalizer = null,
        private readonly string $namespace = 'rate_limit',
    ) {
        if ($this->maxAttempts < 1 || $this->decaySeconds < 1) {
            throw new InvalidArgumentException('Rate limit and decay must be positive integers.');
        }

        $this->keyResolver   = $keyResolver === null ? null : Closure::fromCallable($keyResolver);
        $this->keyNormalizer = $keyNormalizer ?? new HashRateLimitKeyNormalizer();
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $key = $this->resolveKey($request);

        $result = $this->limiter->hit($key, $this->maxAttempts, $this->decaySeconds);
        if (!$result->allowed) {
            throw new TooManyRequestsHttpException(
                message: 'Too Many Requests',
                headers: [
                    'Retry-After'           => (string) $result->retryAfterSeconds,
                    'X-RateLimit-Limit'     => (string) $result->limit,
                    'X-RateLimit-Remaining' => (string) $result->remaining,
                    'X-RateLimit-Reset'     => (string) $result->resetAt,
                ],
            );
        }

        $response = $handler->handle($request);

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $result->limit)
            ->withHeader('X-RateLimit-Remaining', (string) $result->remaining)
            ->withHeader('X-RateLimit-Reset', (string) $result->resetAt);
    }

    private function resolveKey(ServerRequestInterface $request): string
    {
        if ($this->keyResolver !== null) {
            $rawKey = ($this->keyResolver)($request);
        } else {
            $ip     = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
            $path   = $request->getUri()->getPath();
            $rawKey = sprintf('%s|%s', $ip, $path);
        }

        return $this->keyNormalizer->normalize($rawKey, $this->namespace);
    }
}
