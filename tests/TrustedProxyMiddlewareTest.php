<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Tests;

use InvalidArgumentException;
use PhpSoftBox\Application\Middleware\TrustedProxyMiddleware;
use PhpSoftBox\Http\Message\Response;
use PhpSoftBox\Http\Message\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(TrustedProxyMiddleware::class)]
#[CoversMethod(TrustedProxyMiddleware::class, 'process')]
final class TrustedProxyMiddlewareTest extends TestCase
{
    /**
     * Проверим, что от клиента не из списка прокси заголовки `X-Forwarded-*` игнорируются.
     *
     * @see TrustedProxyMiddleware::process()
     */
    #[Test]
    public function ignoresForwardedHeadersFromUntrustedClient(): void
    {
        $request = $this->handle(new TrustedProxyMiddleware(['10.0.0.1']), $this->request('203.0.113.7', [
            'X-Forwarded-For'   => '1.2.3.4',
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host'  => 'evil.example',
        ]));

        self::assertSame('203.0.113.7', $request->getServerParams()['REMOTE_ADDR']);
        self::assertSame('http://app.internal:8080/reset', (string) $request->getUri());
    }

    /**
     * Проверим, что от доверенного прокси подставляются IP клиента, схема и host, а порт бэкенда не протекает в URL.
     *
     * @see TrustedProxyMiddleware::process()
     */
    #[Test]
    public function appliesForwardedHeadersFromTrustedProxy(): void
    {
        $request = $this->handle(new TrustedProxyMiddleware(['10.0.0.0/8']), $this->request('10.0.0.5', [
            'X-Forwarded-For'   => '198.51.100.20',
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host'  => 'shop.example.com',
        ]));

        self::assertSame('198.51.100.20', $request->getServerParams()['REMOTE_ADDR']);
        self::assertSame('10.0.0.5', $request->getAttribute(TrustedProxyMiddleware::ATTRIBUTE_PROXY_ADDR));
        self::assertSame('https://shop.example.com/reset', (string) $request->getUri());
        self::assertSame('shop.example.com', $request->getHeaderLine('Host'));
    }

    /**
     * Проверим, что подделанный клиентом левый адрес цепочки не принимается: IP клиента — первый справа адрес, который
     * не является доверенным прокси.
     *
     * @see TrustedProxyMiddleware::process()
     */
    #[Test]
    public function takesRightmostUntrustedAddress(): void
    {
        $request = $this->handle(new TrustedProxyMiddleware(['10.0.0.1', '10.0.0.2']), $this->request('10.0.0.1', [
            // Клиент прислал «1.1.1.1», прокси дописали его реальный адрес и адрес первого прокси.
            'X-Forwarded-For' => '1.1.1.1, 198.51.100.20, 10.0.0.2',
        ]));

        self::assertSame('198.51.100.20', $request->getServerParams()['REMOTE_ADDR']);
    }

    /**
     * Проверим, что работают IPv6-диапазоны и порт из `X-Forwarded-Port`.
     *
     * @see TrustedProxyMiddleware::process()
     */
    #[Test]
    public function supportsIpv6RangesAndForwardedPort(): void
    {
        $request = $this->handle(new TrustedProxyMiddleware(['fd00::/8']), $this->request('fd12::1', [
            'X-Forwarded-For'   => '2001:db8::7',
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Port'  => '8443',
        ]));

        self::assertSame('2001:db8::7', $request->getServerParams()['REMOTE_ADDR']);
        self::assertSame('https://app.internal:8443/reset', (string) $request->getUri());
    }

    /**
     * Проверим, что некорректный адрес в списке прокси отклоняется при создании.
     *
     * @see TrustedProxyMiddleware::__construct()
     */
    #[Test]
    public function rejectsInvalidProxyRange(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TrustedProxyMiddleware(['10.0.0.0/33']);
    }

    /**
     * @param array<string, string> $headers
     */
    private function request(string $remoteAddr, array $headers): ServerRequest
    {
        return new ServerRequest('GET', 'http://app.internal:8080/reset', $headers, serverParams: ['REMOTE_ADDR' => $remoteAddr]);
    }

    private function handle(TrustedProxyMiddleware $middleware, ServerRequestInterface $request): ServerRequestInterface
    {
        $handler = new class () implements RequestHandlerInterface {
            public ?ServerRequestInterface $request = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->request = $request;

                return new Response(200);
            }
        };

        $middleware->process($request, $handler);

        self::assertNotNull($handler->request);

        return $handler->request;
    }
}
