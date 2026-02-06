<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Exception;

use RuntimeException;
use Throwable;

class HttpException extends RuntimeException implements HttpExceptionInterface
{
    /**
     * @param array<string, string|string[]> $headers
     */
    public function __construct(
        private readonly int $statusCode,
        private readonly string $displayMessage = '',
        private readonly array $headers = [],
        private readonly ?string $title = null,
        private readonly ?string $debugMessage = null,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($this->debugMessage ?? $this->displayMessage, $code, $previous);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, string|string[]>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function message(): string
    {
        return $this->displayMessage;
    }

    public function title(): ?string
    {
        return $this->title;
    }

    public function debugMessage(): ?string
    {
        return $this->debugMessage;
    }
}
