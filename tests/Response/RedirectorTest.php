<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Tests\Response;

use PhpSoftBox\Application\Response\Redirector;
use PhpSoftBox\Application\Response\RedirectResponse;
use PhpSoftBox\Application\Tests\Fixtures\ArraySession;
use PhpSoftBox\Http\Message\ResponseFactory;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Http\Message\Uri;
use PhpSoftBox\Router\RouteCollector;
use PhpSoftBox\Router\UrlGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Redirector::class)]
#[CoversClass(RedirectResponse::class)]
#[CoversMethod(Redirector::class, 'toRoute')]
#[CoversMethod(Redirector::class, 'back')]
#[CoversMethod(RedirectResponse::class, 'withFlash')]
final class RedirectorTest extends TestCase
{
    /**
     * Проверим, что для не-GET запроса редирект на маршрут отдаёт 303.
     *
     * @see Redirector::toRoute()
     */
    #[Test]
    public function toRouteUses303ForNonGetRequests(): void
    {
        $routes = new RouteCollector();

        $routes->get('/users/{id}', static fn () => null)->name('users.show');

        $request = new ServerRequest('PUT', new Uri('https://example.test/users/5'));

        $redirector = new Redirector(new ResponseFactory(), new ArraySession(), $request, new UrlGenerator($routes));

        $response = $redirector->toRoute('users.show', ['id' => 5])->response();

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/users/5', $response->getHeaderLine('Location'));
    }

    /**
     * Проверим, что без генератора URL редирект на маршрут невозможен.
     *
     * @see Redirector::toRoute()
     */
    #[Test]
    public function toRouteThrowsWithoutUrlGenerator(): void
    {
        $redirector = new Redirector(new ResponseFactory(), new ArraySession());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('UrlGenerator is not set for Redirector.');

        $redirector->toRoute('users.show', ['id' => 5]);
    }

    /**
     * Проверим, что Referer того же origin используется как адрес возврата.
     *
     * @see Redirector::back()
     */
    #[Test]
    public function backUsesSameOriginReferer(): void
    {
        self::assertSame('https://example.test/orders?page=2', $this->backLocation('https://example.test/orders?page=2'));
    }

    /**
     * Проверим, что относительный Referer считается тем же origin.
     *
     * @see Redirector::back()
     */
    #[Test]
    public function backUsesRelativeReferer(): void
    {
        self::assertSame('/orders', $this->backLocation('/orders'));
    }

    /**
     * Проверим, что Referer чужого домена не используется: иначе это открытый редирект.
     *
     * @see Redirector::back()
     */
    #[Test]
    public function backIgnoresForeignReferer(): void
    {
        self::assertSame('/fallback', $this->backLocation('https://evil.test/phishing'));
    }

    /**
     * Проверим, что адрес без схемы вида «//host» не используется: браузер уведёт на другой host.
     *
     * @see Redirector::back()
     */
    #[Test]
    public function backIgnoresProtocolRelativeReferer(): void
    {
        self::assertSame('/fallback', $this->backLocation('//evil.test/phishing'));
        self::assertSame('/fallback', $this->backLocation('/\\evil.test/phishing'));
    }

    /**
     * Проверим, что другая схема или порт — другой origin, а явный порт по умолчанию — тот же.
     *
     * @see Redirector::back()
     */
    #[Test]
    public function backComparesSchemeAndPort(): void
    {
        self::assertSame('/fallback', $this->backLocation('http://example.test/orders'));
        self::assertSame('/fallback', $this->backLocation('https://example.test:8443/orders'));
        self::assertSame('https://example.test:443/orders', $this->backLocation('https://example.test:443/orders'));
    }

    /**
     * Проверим, что flash-данные редиректа попадают в сессию.
     *
     * @see RedirectResponse::withFlash()
     */
    #[Test]
    public function withFlashStoresValueInSession(): void
    {
        $session = new ArraySession();

        $redirector = new Redirector(new ResponseFactory(), $session);

        $redirector->to('/orders')->withFlash('success', 'Сохранено');

        self::assertSame('Сохранено', $session->getFlash('success'));
    }

    private function backLocation(string $referer): string
    {
        $request = new ServerRequest('POST', new Uri('https://example.test/orders/5'))->withHeader('Referer', $referer);

        return new Redirector(new ResponseFactory(), new ArraySession(), $request)
            ->back('/fallback')
            ->response()
            ->getHeaderLine('Location');
    }
}
