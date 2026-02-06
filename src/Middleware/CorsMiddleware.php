<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function explode;
use function implode;
use function in_array;
use function strtoupper;
use function trim;

final class CorsMiddleware implements MiddlewareInterface
{
    /**
     * @param string[] $allowedOrigins
     * @param string[] $allowedMethods
     * @param string[] $allowedHeaders
     * @param string[] $exposedHeaders
     */
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private array $allowedOrigins = ['*'],
        private array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        private array $allowedHeaders = ['Content-Type', 'Authorization', 'X-Requested-With'],
        private array $exposedHeaders = [],
        private bool $allowCredentials = false,
        private ?int $maxAge = null,
    ) {
        $this->allowedMethods = array_map('strtoupper', $this->allowedMethods);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $origin = trim($request->getHeaderLine('Origin'));
        if ($origin === '') {
            return $handler->handle($request);
        }

        if (!$this->isOriginAllowed($origin)) {
            return $handler->handle($request);
        }

        $isPreflight = strtoupper($request->getMethod()) === 'OPTIONS'
            && $request->hasHeader('Access-Control-Request-Method');

        if ($isPreflight) {
            $response = $this->responseFactory->createResponse(204);

            return $this->withCorsHeaders($response, $origin, $request);
        }

        $response = $handler->handle($request);

        return $this->withCorsHeaders($response, $origin, $request);
    }

    private function withCorsHeaders(ResponseInterface $response, string $origin, ServerRequestInterface $request): ResponseInterface
    {
        $allowOrigin = $this->resolveAllowOrigin($origin);

        $response = $response->withHeader('Access-Control-Allow-Origin', $allowOrigin);

        if ($allowOrigin !== '*') {
            $response = $response->withHeader('Vary', 'Origin');
        }

        if ($this->allowCredentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        $allowMethods = $this->allowedMethods;
        if ($allowMethods !== []) {
            $response = $response->withHeader('Access-Control-Allow-Methods', implode(', ', $allowMethods));
        }

        $allowHeaders = $this->resolveAllowHeaders($request);
        if ($allowHeaders !== []) {
            $response = $response->withHeader('Access-Control-Allow-Headers', implode(', ', $allowHeaders));
        }

        if ($this->exposedHeaders !== []) {
            $response = $response->withHeader('Access-Control-Expose-Headers', implode(', ', $this->exposedHeaders));
        }

        if ($this->maxAge !== null) {
            $response = $response->withHeader('Access-Control-Max-Age', (string) $this->maxAge);
        }

        return $response;
    }

    private function resolveAllowOrigin(string $origin): string
    {
        if (in_array('*', $this->allowedOrigins, true)) {
            if ($this->allowCredentials) {
                return $origin;
            }

            return '*';
        }

        return $origin;
    }

    /**
     * @return string[]
     */
    private function resolveAllowHeaders(ServerRequestInterface $request): array
    {
        if ($this->allowedHeaders !== []) {
            return $this->allowedHeaders;
        }

        $requested = trim($request->getHeaderLine('Access-Control-Request-Headers'));
        if ($requested === '') {
            return [];
        }

        $parts = array_map('trim', explode(',', $requested));

        return array_values(array_unique(array_filter($parts)));
    }

    private function isOriginAllowed(string $origin): bool
    {
        if (in_array('*', $this->allowedOrigins, true)) {
            return true;
        }

        return in_array($origin, $this->allowedOrigins, true);
    }
}
