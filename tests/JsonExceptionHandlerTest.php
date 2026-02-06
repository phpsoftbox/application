<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Tests;

use PhpSoftBox\Application\ErrorHandler\AbstractExceptionHandler;
use PhpSoftBox\Application\ErrorHandler\JsonExceptionHandler;
use PhpSoftBox\Application\Exception\CodedHttpException;
use PhpSoftBox\Http\Message\ResponseFactory;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Http\Message\StreamFactory;
use PhpSoftBox\Router\Exception\InvalidRouteParameterException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_decode;

#[CoversClass(AbstractExceptionHandler::class)]
#[CoversClass(JsonExceptionHandler::class)]
final class JsonExceptionHandlerTest extends TestCase
{
    /**
     * Проверяет, что InvalidRouteParameterException маппится в 404 с title/message Not Found в прод-режиме.
     */
    #[Test]
    public function invalidRouteParameterReturnsNotFoundWhenDetailsHidden(): void
    {
        $handler = new JsonExceptionHandler(new ResponseFactory(), new StreamFactory(), includeDetails: false);
        $request = new ServerRequest('GET', 'https://example.com/users/create');

        $response = $handler->handle(new InvalidRouteParameterException('Invalid parameter: id'), $request);

        self::assertSame(404, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);
        self::assertIsArray($payload);
        self::assertSame('not_found', $payload['code'] ?? null);
        self::assertSame('Not Found', $payload['title'] ?? null);
        self::assertSame('Not Found', $payload['message'] ?? null);
    }

    /**
     * Проверяет, что InvalidRouteParameterException отдает debug_message в debug-режиме.
     */
    #[Test]
    public function invalidRouteParameterKeepsMessageWhenDetailsEnabled(): void
    {
        $handler = new JsonExceptionHandler(new ResponseFactory(), new StreamFactory(), includeDetails: true);
        $request = new ServerRequest('GET', 'https://example.com/users/create');

        $response = $handler->handle(new InvalidRouteParameterException('Invalid parameter: id'), $request);

        self::assertSame(404, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);
        self::assertIsArray($payload);
        self::assertSame('Not Found', $payload['title'] ?? null);
        self::assertSame('Not Found', $payload['message'] ?? null);
        self::assertSame('Invalid parameter: id', $payload['debug_message'] ?? null);
        self::assertIsString($payload['trace'] ?? null);
        self::assertNotSame('', $payload['trace'] ?? null);
    }

    #[Test]
    public function codedHttpExceptionExposesClientSafeCodeAndDetails(): void
    {
        $handler = new JsonExceptionHandler(new ResponseFactory(), new StreamFactory(), includeDetails: false);
        $request = new ServerRequest('GET', 'https://example.com/api');

        $exception = new CodedHttpException(
            statusCode: 406,
            errorCode: 'unsupported_api_version',
            displayMessage: 'Requested API version is not supported.',
            details: ['supported' => ['1.0.0', '1.1.0']],
            title: 'Unsupported API version',
            debugMessage: 'Internal negotiation diagnostic.',
        );

        $response = $handler->handle($exception, $request);
        $payload  = json_decode((string) $response->getBody(), true);

        self::assertSame(406, $response->getStatusCode());
        self::assertIsArray($payload);
        self::assertSame('unsupported_api_version', $payload['code'] ?? null);
        self::assertSame('Unsupported API version', $payload['title'] ?? null);
        self::assertSame('Requested API version is not supported.', $payload['message'] ?? null);
        self::assertSame(['supported' => ['1.0.0', '1.1.0']], $payload['details'] ?? null);
        self::assertArrayNotHasKey('debug_message', $payload);
    }
}
