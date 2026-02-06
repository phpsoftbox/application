<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Exception;

final class TooManyRequestsHttpException extends HttpException
{
    public function __construct(
        string $message = 'Too Many Requests',
        array $headers = [],
        ?string $title = null,
        ?string $debugMessage = null,
    ) {
        parent::__construct(429, $message, $headers, $title, $debugMessage);
    }
}
