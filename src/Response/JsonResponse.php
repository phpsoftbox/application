<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Response;

use InvalidArgumentException;

use function json_encode;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final class JsonResponse extends AbstractContentResponse
{
    /**
     * @param array<string, string|string[]> $headers
     */
    public function __construct(
        mixed $data,
        int $status = 200,
        array $headers = [],
        int $encodingOptions = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        string $protocolVersion = '1.1',
        string $reasonPhrase = '',
    ) {
        $payload = json_encode($data, $encodingOptions);
        if ($payload === false) {
            throw new InvalidArgumentException('Unable to encode JSON response payload.');
        }

        $headers = self::withContentType($headers, 'application/json; charset=utf-8');

        parent::__construct($status, $headers, $payload, $protocolVersion, $reasonPhrase);
    }
}
