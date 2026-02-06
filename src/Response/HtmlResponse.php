<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Response;

final class HtmlResponse extends AbstractContentResponse
{
    /**
     * @param array<string, string|string[]> $headers
     */
    public function __construct(
        string $html,
        int $status = 200,
        array $headers = [],
        string $protocolVersion = '1.1',
        string $reasonPhrase = '',
    ) {
        $headers = self::withContentType($headers, 'text/html; charset=utf-8');

        parent::__construct($status, $headers, $html, $protocolVersion, $reasonPhrase);
    }
}
