<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Exception;

final class BadRequestHttpException extends HttpException
{
    public function __construct(
        string $message = 'Bad Request',
        array $headers = [],
        ?string $title = null,
        ?string $debugMessage = null,
    ) {
        parent::__construct(400, $message, $headers, $title, $debugMessage);
    }
}
