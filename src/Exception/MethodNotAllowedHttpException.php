<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Exception;

final class MethodNotAllowedHttpException extends HttpException
{
    /**
     * @param string[] $allowed
     */
    public function __construct(
        array $allowed,
        string $message = 'Method Not Allowed',
        array $headers = [],
        ?string $title = null,
        ?string $debugMessage = null,
    ) {
        $headers = ['Allow' => $allowed] + $headers;

        parent::__construct(405, $message, $headers, $title, $debugMessage);
    }
}
