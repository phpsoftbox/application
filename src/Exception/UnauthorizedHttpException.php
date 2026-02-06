<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Exception;

final class UnauthorizedHttpException extends HttpException
{
    public function __construct(
        string $message = 'Unauthorized',
        array $headers = [],
        ?string $title = null,
        ?string $debugMessage = null,
    ) {
        parent::__construct(401, $message, $headers, $title, $debugMessage);
    }
}
