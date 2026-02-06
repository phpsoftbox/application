<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\ErrorHandler\Mapper;

use PhpSoftBox\Application\ErrorHandler\ExceptionMapperInterface;
use PhpSoftBox\Application\Exception\MethodNotAllowedHttpException;
use PhpSoftBox\Application\Exception\NotFoundHttpException;
use PhpSoftBox\Router\Exception\InvalidRouteParameterException;
use PhpSoftBox\Router\Exception\MethodNotAllowedException;
use PhpSoftBox\Router\Exception\RouteNotFoundException;
use Throwable;

final class RouteExceptionMapper implements ExceptionMapperInterface
{
    public function map(Throwable $exception): ?Throwable
    {
        if ($exception instanceof MethodNotAllowedException) {
            return new MethodNotAllowedHttpException(
                allowed: $exception->allowedMethods(),
                message: 'Method Not Allowed',
                title: 'Method Not Allowed',
                debugMessage: $exception->getMessage() !== '' ? $exception->getMessage() : null,
            );
        }

        if ($exception instanceof InvalidRouteParameterException || $exception instanceof RouteNotFoundException) {
            return new NotFoundHttpException(
                message: 'Not Found',
                title: 'Not Found',
                debugMessage: $exception->getMessage() !== '' ? $exception->getMessage() : null,
            );
        }

        return null;
    }
}
