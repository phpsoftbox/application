<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Tests;

use PhpSoftBox\Application\ErrorHandler\Mapper\RouteExceptionMapper;
use PhpSoftBox\Application\Exception\MethodNotAllowedHttpException;
use PhpSoftBox\Application\Exception\NotFoundHttpException;
use PhpSoftBox\Router\Exception\InvalidRouteParameterException;
use PhpSoftBox\Router\Exception\MethodNotAllowedException;
use PhpSoftBox\Router\Exception\RouteNotFoundException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RouteExceptionMapperTest extends TestCase
{
    /**
     * Проверяет маппинг RouteNotFoundException в NotFoundHttpException с debugMessage.
     */
    #[Test]
    public function mapsRouteNotFoundToNotFoundHttpException(): void
    {
        $mapper = new RouteExceptionMapper();

        $mapped = $mapper->map(new RouteNotFoundException('Route with name test not found'));

        self::assertInstanceOf(NotFoundHttpException::class, $mapped);
        self::assertSame(404, $mapped->statusCode());
        self::assertSame('Not Found', $mapped->title());
        self::assertSame('Not Found', $mapped->message());
        self::assertSame('Route with name test not found', $mapped->debugMessage());
    }

    /**
     * Проверяет маппинг InvalidRouteParameterException в NotFoundHttpException.
     */
    #[Test]
    public function mapsInvalidRouteParameterToNotFoundHttpException(): void
    {
        $mapper = new RouteExceptionMapper();

        $mapped = $mapper->map(new InvalidRouteParameterException('Entity not found for parameter: company'));

        self::assertInstanceOf(NotFoundHttpException::class, $mapped);
        self::assertSame('Not Found', $mapped->message());
        self::assertSame('Entity not found for parameter: company', $mapped->debugMessage());
    }

    /**
     * Проверяет маппинг MethodNotAllowedException в MethodNotAllowedHttpException с Allow-заголовком.
     */
    #[Test]
    public function mapsMethodNotAllowedToMethodNotAllowedHttpException(): void
    {
        $mapper = new RouteExceptionMapper();

        $mapped = $mapper->map(new MethodNotAllowedException(['GET', 'POST'], 'Only GET and POST are allowed'));

        self::assertInstanceOf(MethodNotAllowedHttpException::class, $mapped);
        self::assertSame(405, $mapped->statusCode());
        self::assertSame(['GET', 'POST'], $mapped->headers()['Allow'] ?? []);
        self::assertSame('Method Not Allowed', $mapped->message());
        self::assertSame('Only GET and POST are allowed', $mapped->debugMessage());
    }

    /**
     * Проверяет, что нероутерные исключения маппер не изменяет.
     */
    #[Test]
    public function returnsNullForUnsupportedExceptions(): void
    {
        $mapper = new RouteExceptionMapper();

        self::assertNull($mapper->map(new RuntimeException('noop')));
    }
}
