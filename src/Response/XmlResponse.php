<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Response;

final class XmlResponse extends AbstractContentResponse
{
    /**
     * @param array<string, string|string[]> $headers
     */
    public function __construct(
        string $xml,
        int $status = 200,
        array $headers = [],
        string $protocolVersion = '1.1',
        string $reasonPhrase = '',
    ) {
        $headers = self::withContentType($headers, 'application/xml; charset=utf-8');

        parent::__construct($status, $headers, $xml, $protocolVersion, $reasonPhrase);
    }
}
