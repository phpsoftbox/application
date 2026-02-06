<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\ErrorHandler;

use PhpSoftBox\Application\Exception\CodedHttpExceptionInterface;
use PhpSoftBox\Application\Exception\HttpExceptionInterface;
use PhpSoftBox\Router\Exception\InvalidRouteParameterException;
use PhpSoftBox\Router\Exception\MethodNotAllowedException;
use PhpSoftBox\Router\Exception\RouteNotFoundException;
use PhpSoftBox\Validator\Exception\ValidationException;
use Throwable;

use function method_exists;
use function trim;

abstract class AbstractExceptionHandler implements ExceptionHandlerInterface
{
    public function __construct(
        protected readonly bool $includeDetails = false,
    ) {
    }

    /**
     * @return array{status:int, headers: array<string, string|string[]>}
     */
    protected function resolveStatusAndHeaders(Throwable $exception): array
    {
        $status  = 500;
        $headers = [];

        if ($exception instanceof HttpExceptionInterface) {
            $status  = $exception->statusCode();
            $headers = $exception->headers();
        } elseif (method_exists($exception, 'statusCode') && method_exists($exception, 'headers')) {
            $status  = (int) $exception->statusCode();
            $headers = (array) $exception->headers();
        }

        if ($exception instanceof ValidationException) {
            $status = 422;
        }
        if ($exception instanceof InvalidRouteParameterException) {
            $status = 404;
        }
        if ($exception instanceof RouteNotFoundException) {
            $status = 404;
        }
        if ($exception instanceof MethodNotAllowedException) {
            $status  = 405;
            $headers = ['Allow' => $exception->allowedMethods()] + $headers;
        }

        return ['status' => $status, 'headers' => $headers];
    }

    protected function resolveTitle(Throwable $exception, int $status): string
    {
        if ($exception instanceof HttpExceptionInterface) {
            $title = trim((string) $exception->title());
            if ($title !== '') {
                return $title;
            }
        }

        return match ($status) {
            400     => 'Bad Request',
            401     => 'Unauthorized',
            403     => 'Forbidden',
            404     => 'Not Found',
            405     => 'Method Not Allowed',
            413     => 'Payload Too Large',
            422     => 'Validation Failed',
            429     => 'Too Many Requests',
            default => $status >= 500 ? 'Internal Server Error' : 'Error',
        };
    }

    protected function resolveErrorCode(Throwable $exception, int $status): string
    {
        if ($exception instanceof CodedHttpExceptionInterface) {
            return $exception->errorCode();
        }

        if ($exception instanceof ValidationException) {
            return 'validation_failed';
        }

        return match ($status) {
            400     => 'bad_request',
            401     => 'unauthenticated',
            403     => 'forbidden',
            404     => 'not_found',
            405     => 'method_not_allowed',
            413     => 'payload_too_large',
            422     => 'validation_failed',
            429     => 'rate_limit_exceeded',
            default => $status >= 500 ? 'internal_error' : 'http_error',
        };
    }

    protected function resolveMessage(Throwable $exception, int $status): string
    {
        if ($exception instanceof ValidationException) {
            return $exception->getMessage();
        }

        if ($exception instanceof InvalidRouteParameterException || $exception instanceof RouteNotFoundException) {
            return 'Not Found';
        }
        if ($exception instanceof MethodNotAllowedException) {
            return 'Method Not Allowed';
        }

        if ($exception instanceof HttpExceptionInterface) {
            $message = trim($exception->message());
            if ($message !== '') {
                return $message;
            }
        }

        if (
            $exception->getMessage() !== ''
            && (
                $exception instanceof HttpExceptionInterface
                || (method_exists($exception, 'statusCode') && method_exists($exception, 'headers'))
            )
        ) {
            return $exception->getMessage();
        }

        if ($status === 404 && !$this->includeDetails) {
            return 'Not Found';
        }

        if ($status >= 500 && !$this->includeDetails) {
            return 'Internal Server Error';
        }

        return $exception->getMessage() !== '' ? $exception->getMessage() : 'Error';
    }

    protected function resolveDebugMessage(Throwable $exception): ?string
    {
        if (!$this->includeDetails) {
            return null;
        }

        if ($exception instanceof HttpExceptionInterface) {
            $debugMessage = trim((string) $exception->debugMessage());
            if ($debugMessage !== '') {
                return $debugMessage;
            }
        }

        $message = trim($exception->getMessage());

        return $message !== '' ? $message : null;
    }
}
