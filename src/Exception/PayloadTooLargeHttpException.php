<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Exception;

final class PayloadTooLargeHttpException extends HttpException
{
    public function __construct(
        string $message = 'Payload Too Large',
        array $headers = [],
        ?string $title = null,
        ?string $debugMessage = null,
    ) {
        parent::__construct(413, $message, $headers, $title, $debugMessage);
    }
}
