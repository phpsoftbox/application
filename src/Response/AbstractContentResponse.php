<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Response;

use PhpSoftBox\Http\Message\Response;

use function strtolower;

abstract class AbstractContentResponse extends Response
{
    /**
     * @param array<string, string|string[]> $headers
     * @return array<string, string|string[]>
     */
    protected static function withContentType(array $headers, string $contentType): array
    {
        foreach ($headers as $name => $_) {
            if (strtolower((string) $name) === 'content-type') {
                return $headers;
            }
        }

        $headers['Content-Type'] = $contentType;

        return $headers;
    }
}
