<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Response;

use Closure;
use PhpSoftBox\Router\UrlGeneratorInterface;
use PhpSoftBox\Session\SessionInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

use function in_array;
use function parse_url;
use function str_starts_with;
use function strtolower;
use function strtoupper;
use function trim;

/**
 * Редиректы: на путь, на именованный маршрут и назад (на Referer того же origin).
 */
final class Redirector
{
    private ?ServerRequestInterface $request;
    private ?Closure $requestProvider;

    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly SessionInterface $session,
        ?ServerRequestInterface $request = null,
        private readonly ?UrlGeneratorInterface $urlGenerator = null,
        ?callable $requestProvider = null,
    ) {
        $this->request         = $request;
        $this->requestProvider = $requestProvider !== null
            ? Closure::fromCallable($requestProvider)
            : null;
    }

    public function setRequest(?ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    public function to(string $path, ?int $status = null): RedirectResponse
    {
        if ($status === null) {
            $request = $this->resolveRequest();
            if ($request !== null) {
                $status = $this->redirectStatus($request->getMethod());
            } else {
                $status = 302;
            }
        }

        $response = $this->responseFactory->createResponse($status)->withHeader('Location', $path);

        return new RedirectResponse($response, $this->session);
    }

    public function toRoute(string $name, array $params = [], ?int $status = null): RedirectResponse
    {
        if ($this->urlGenerator === null) {
            throw new RuntimeException('UrlGenerator is not set for Redirector.');
        }

        return $this->to($this->urlGenerator->generate($name, $params), $status);
    }

    public function back(string $fallback = '/'): RedirectResponse
    {
        $request = $this->resolveRequest();
        if ($request === null) {
            return $this->to($fallback);
        }

        $referer = trim($request->getHeaderLine('Referer'));
        $target  = $referer !== '' && $this->isSameOrigin($referer, $request) ? $referer : $fallback;

        $status = $this->redirectStatus($request->getMethod());

        return $this->to($target, $status);
    }

    /**
     * Referer принимается только с того же origin: иначе ссылка со стороннего сайта уводила бы пользователя на чужой
     * домен (открытый редирект). Относительный путь — тот же origin.
     */
    private function isSameOrigin(string $url, ServerRequestInterface $request): bool
    {
        // «//host/path» и «/\host/path» браузер трактует как абсолютный адрес другого host.
        if (str_starts_with($url, '//') || str_starts_with($url, '/\\')) {
            return false;
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return false;
        }

        if (!isset($parts['host'])) {
            return !isset($parts['scheme']) && str_starts_with($url, '/');
        }

        $uri = $request->getUri();

        $scheme = strtolower($parts['scheme'] ?? '');

        return strtolower($parts['host']) === strtolower($uri->getHost())
            && $scheme === strtolower($uri->getScheme())
            && $this->effectivePort($scheme, $parts['port'] ?? null) === $this->effectivePort($scheme, $uri->getPort());
    }

    private function effectivePort(string $scheme, ?int $port): ?int
    {
        return $port ?? match ($scheme) {
            'http'  => 80,
            'https' => 443,
            default => null,
        };
    }

    private function resolveRequest(): ?ServerRequestInterface
    {
        if ($this->requestProvider !== null) {
            $request = ($this->requestProvider)();
            if ($request instanceof ServerRequestInterface) {
                return $request;
            }
        }

        return $this->request;
    }

    private function redirectStatus(string $method): int
    {
        $method = strtoupper($method);

        return in_array($method, ['GET', 'HEAD'], true) ? 302 : 303;
    }
}
