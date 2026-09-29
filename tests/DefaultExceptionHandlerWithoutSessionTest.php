<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Tests;

use PhpSoftBox\Application\ErrorHandler\DefaultExceptionHandler;
use PhpSoftBox\Application\ErrorHandler\ExceptionHandlerInterface;
use PhpSoftBox\Application\Exception\HttpException;
use PhpSoftBox\Http\Message\Response;
use PhpSoftBox\Http\Message\ResponseFactory;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Session\Exception\CsrfTokenMismatchException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

#[CoversClass(DefaultExceptionHandler::class)]
#[CoversMethod(DefaultExceptionHandler::class, 'handle')]
final class DefaultExceptionHandlerWithoutSessionTest extends TestCase
{
    /**
     * Проверим, что CSRF-ошибка для JSON-клиента превращается в HTTP 419, а не в 500.
     *
     * @see DefaultExceptionHandler::handle()
     */
    #[Test]
    public function csrfMismatchForJsonClientIs419(): void
    {
        $fallback = $this->fallback();
        $handler  = new DefaultExceptionHandler($fallback, new ResponseFactory());

        $handler->handle(
            new CsrfTokenMismatchException(),
            new ServerRequest('POST', 'https://example.com/api/items', ['Accept' => 'application/json']),
        );

        self::assertInstanceOf(HttpException::class, $fallback->handled);
        self::assertSame(419, $fallback->handled->statusCode());
    }

    /**
     * Проверим, что обработчик с репортерами работает без сессии (API-приложение): исключение сообщается репортеру и
     * передаётся обработчику по умолчанию.
     *
     * @see DefaultExceptionHandler::handle()
     */
    #[Test]
    public function worksWithoutSession(): void
    {
        $fallback = $this->fallback();
        $reported = [];
        $handler  = new DefaultExceptionHandler(
            $fallback,
            new ResponseFactory(),
            reporters: [static function (Throwable $exception) use (&$reported): void {
                $reported[] = $exception;
            }],
        );

        $exception = new RuntimeException('Boom.');

        $response = $handler->handle($exception, new ServerRequest('GET', 'https://example.com/'));

        self::assertSame(500, $response->getStatusCode());
        self::assertSame([$exception], $reported);
        self::assertSame($exception, $fallback->handled);
    }

    /**
     * @return ExceptionHandlerInterface&object{handled: ?Throwable}
     */
    private function fallback(): ExceptionHandlerInterface
    {
        return new class () implements ExceptionHandlerInterface {
            public ?Throwable $handled = null;

            public function handle(Throwable $exception, ServerRequestInterface $request): ResponseInterface
            {
                $this->handled = $exception;

                return new Response($exception instanceof HttpException ? $exception->statusCode() : 500);
            }
        };
    }
}
