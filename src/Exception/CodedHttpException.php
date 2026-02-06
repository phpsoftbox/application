<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Exception;

use InvalidArgumentException;
use Throwable;

use function preg_match;
use function trim;

final class CodedHttpException extends HttpException implements CodedHttpExceptionInterface
{
    /**
     * @param array<string, mixed> $details
     * @param array<string, string|string[]> $headers
     */
    public function __construct(
        int $statusCode,
        private readonly string $errorCode,
        string $displayMessage = '',
        private readonly array $details = [],
        array $headers = [],
        ?string $title = null,
        ?string $debugMessage = null,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        $normalizedCode = trim($this->errorCode);
        if (
            $normalizedCode === ''
            || $normalizedCode !== $this->errorCode
            || preg_match('/^[a-z][a-z0-9_]*$/D', $normalizedCode) !== 1
        ) {
            throw new InvalidArgumentException('HTTP error code must use lower_snake_case.');
        }

        parent::__construct(
            $statusCode,
            $displayMessage,
            $headers,
            $title,
            $debugMessage,
            $code,
            $previous,
        );
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function details(): array
    {
        return $this->details;
    }
}
