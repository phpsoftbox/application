<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Middleware;

use InvalidArgumentException;
use PhpSoftBox\Http\Message\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function array_reverse;
use function count;
use function ctype_digit;
use function explode;
use function filter_var;
use function in_array;
use function inet_pton;
use function intdiv;
use function is_string;
use function ord;
use function preg_match;
use function strlen;
use function strtolower;
use function substr;
use function trim;

use const FILTER_VALIDATE_IP;

/**
 * Доверенные прокси: учитывает `X-Forwarded-*`, только если запрос пришёл от прокси из списка.
 *
 * Ставится первым в стеке. Если `REMOTE_ADDR` — доверенный прокси (IP или CIDR, IPv4 и IPv6):
 * - в `REMOTE_ADDR` подставляется IP клиента: первый справа адрес `X-Forwarded-For`, который сам не доверенный прокси
 *   (адрес прокси сохраняется в атрибуте `proxy_addr`);
 * - схема, host и порт URI — из `X-Forwarded-Proto`, `X-Forwarded-Host`, `X-Forwarded-Port`.
 *
 * Иначе заголовки игнорируются: их подделывает сам клиент. Код ниже по стеку (Auth, Session, Router, rate limiter)
 * читает только `REMOTE_ADDR` и URI запроса.
 */
final readonly class TrustedProxyMiddleware implements MiddlewareInterface
{
    public const string ATTRIBUTE_PROXY_ADDR = 'proxy_addr';

    /**
     * @var list<array{0: string, 1: int}> адрес в бинарном виде и длина маски
     */
    private array $proxies;

    /**
     * @param list<string> $trustedProxies IP или CIDR: `10.0.0.1`, `10.0.0.0/8`, `fd00::/8`
     */
    public function __construct(array $trustedProxies = [])
    {
        $proxies = [];
        foreach ($trustedProxies as $proxy) {
            $proxy = trim($proxy);
            if ($proxy !== '') {
                $proxies[] = $this->parseRange($proxy);
            }
        }

        $this->proxies = $proxies;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $remote = $request->getServerParams()['REMOTE_ADDR'] ?? null;
        if (!is_string($remote) || !$this->isTrusted($remote)) {
            return $handler->handle($request);
        }

        $server                = $request->getServerParams();
        $server['REMOTE_ADDR'] = $this->clientIp($request, $remote);

        $request = $this->withServerParams($request, $server)
            ->withAttribute(self::ATTRIBUTE_PROXY_ADDR, $remote)
            ->withUri($this->forwardedUri($request));

        return $handler->handle($request);
    }

    private function clientIp(ServerRequestInterface $request, string $remote): string
    {
        $chain = [];
        foreach (explode(',', $request->getHeaderLine('X-Forwarded-For')) as $address) {
            $address = trim($address);
            if ($address !== '') {
                $chain[] = $address;
            }
        }

        // Справа налево: каждый доверенный прокси дописывает адрес того, от кого получил запрос.
        foreach (array_reverse($chain) as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP) === false) {
                return $remote;
            }

            if (!$this->isTrusted($address)) {
                return $address;
            }
        }

        return $chain !== [] ? $chain[0] : $remote;
    }

    private function forwardedUri(ServerRequestInterface $request): UriInterface
    {
        $uri = $request->getUri();

        $proto = strtolower($this->firstValue($request->getHeaderLine('X-Forwarded-Proto')));
        if (in_array($proto, ['http', 'https'], true)) {
            $uri = $uri->withScheme($proto);
        }

        $host        = $this->firstValue($request->getHeaderLine('X-Forwarded-Host'));
        $port        = null;
        $hostChanged = false;
        if ($host !== '' && preg_match('/^(\[[0-9A-Fa-f:.]+\]|[A-Za-z0-9.-]+)(?::(\d{1,5}))?$/', $host, $match) === 1) {
            $uri         = $uri->withHost($match[1]);
            $port        = isset($match[2]) ? (int) $match[2] : null;
            $hostChanged = true;
        }

        $forwardedPort = $this->firstValue($request->getHeaderLine('X-Forwarded-Port'));
        if ($forwardedPort !== '' && ctype_digit($forwardedPort)) {
            $port = (int) $forwardedPort;
        }

        if ($port !== null && $port > 0 && $port <= 65535) {
            $default = $uri->getScheme() === 'https' ? 443 : 80;
            $uri     = $uri->withPort($port === $default ? null : $port);
        } elseif ($proto !== '' || $hostChanged) {
            // Прокси передал схему или host без порта — порт стандартный, внутренний порт бэкенда не протекает в URL.
            $uri = $uri->withPort(null);
        }

        return $uri;
    }

    private function firstValue(string $header): string
    {
        return trim(explode(',', $header, 2)[0]);
    }

    private function isTrusted(string $address): bool
    {
        $binary = @inet_pton($address);
        if ($binary === false) {
            return false;
        }

        foreach ($this->proxies as [$network, $bits]) {
            if (strlen($network) === strlen($binary) && $this->matches($binary, $network, $bits)) {
                return true;
            }
        }

        return false;
    }

    private function matches(string $address, string $network, int $bits): bool
    {
        $bytes = intdiv($bits, 8);
        if (substr($address, 0, $bytes) !== substr($network, 0, $bytes)) {
            return false;
        }

        $rest = $bits % 8;
        if ($rest === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $rest)) & 0xFF;

        return (ord($address[$bytes]) & $mask) === (ord($network[$bytes]) & $mask);
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function parseRange(string $range): array
    {
        $parts   = explode('/', $range, 2);
        $network = @inet_pton($parts[0]);
        if ($network === false) {
            throw new InvalidArgumentException('Invalid trusted proxy address: ' . $range);
        }

        $max  = strlen($network) * 8;
        $bits = $max;
        if (count($parts) === 2) {
            if (!ctype_digit($parts[1]) || (int) $parts[1] > $max) {
                throw new InvalidArgumentException('Invalid trusted proxy mask: ' . $range);
            }

            $bits = (int) $parts[1];
        }

        return [$network, $bits];
    }

    /**
     * @param array<string, mixed> $server
     */
    private function withServerParams(ServerRequestInterface $request, array $server): ServerRequestInterface
    {
        if ($request instanceof ServerRequest) {
            return $request->withServerParams($server);
        }

        // PSR-7 не позволяет заменить server params — собираем запрос заново.
        $copy = new ServerRequest(
            method: $request->getMethod(),
            uri: $request->getUri(),
            headers: $request->getHeaders(),
            body: $request->getBody(),
            protocolVersion: $request->getProtocolVersion(),
            serverParams: $server,
            cookieParams: $request->getCookieParams(),
            queryParams: $request->getQueryParams(),
            uploadedFiles: $request->getUploadedFiles(),
            parsedBody: $request->getParsedBody(),
            attributes: $request->getAttributes(),
        );

        return $copy->withRequestTarget($request->getRequestTarget());
    }
}
