<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Tests;

use PhpSoftBox\Application\Application;
use PhpSoftBox\Http\Emitter\EmitterInterface;
use PhpSoftBox\Http\Message\Response;
use PhpSoftBox\Http\Message\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(Application::class)]
#[CoversMethod(Application::class, 'run')]
final class ApplicationRunHeadTest extends TestCase
{
    /**
     * Проверим, что ответ на HEAD отправляется без тела, а на GET — с телом.
     *
     * @see Application::run()
     */
    #[Test]
    public function emitsHeadWithoutBody(): void
    {
        $emitter = new class () implements EmitterInterface {
            /** @var list<bool> */
            public array $withoutBody = [];

            public function emit(ResponseInterface $response, bool $withoutBody = false): void
            {
                $this->withoutBody[] = $withoutBody;
            }
        };

        $app = new Application(new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200, [], 'body');
            }
        });

        $app->run(new ServerRequest('HEAD', 'https://example.com/'), $emitter);
        $app->run(new ServerRequest('GET', 'https://example.com/'), $emitter);

        self::assertSame([true, false], $emitter->withoutBody);
    }
}
