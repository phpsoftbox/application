<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Tests;

use PhpSoftBox\Application\Application;
use PhpSoftBox\Application\ErrorHandler\ExceptionHandlerInterface;
use PhpSoftBox\Application\Middleware\ErrorHandlerMiddleware;
use PhpSoftBox\Http\Message\Response;
use PhpSoftBox\Http\Message\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Throwable;

#[CoversClass(Application::class)]
#[CoversMethod(Application::class, 'handle')]
final class ApplicationLazyMiddlewareTest extends TestCase
{
    /**
     * Проверим, что ошибка создания middleware из контейнера проходит через ErrorHandlerMiddleware выше по стеку:
     * middleware создаются в момент вызова, а не до цепочки.
     *
     * @see Application::handle()
     */
    #[Test]
    public function middlewareCreationErrorIsHandled(): void
    {
        $errors = new ErrorHandlerMiddleware(new class () implements ExceptionHandlerInterface {
            public function handle(Throwable $exception, ServerRequestInterface $request): ResponseInterface
            {
                return new Response(503, ['X-Error' => $exception->getMessage()]);
            }
        });

        $container = new class () implements ContainerInterface {
            public function get(string $id): mixed
            {
                throw new RuntimeException('Middleware dependency is missing.');
            }

            public function has(string $id): bool
            {
                return true;
            }
        };

        $app = new Application(new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200);
            }
        }, container: $container);
        $app->add($errors, 100);
        $app->add('broken.middleware');

        $response = $app->handle(new ServerRequest('GET', 'https://example.com/'));

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('Middleware dependency is missing.', $response->getHeaderLine('X-Error'));
    }
}
