<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Exception;

interface CodedHttpExceptionInterface extends HttpExceptionInterface
{
    public function errorCode(): string;

    /**
     * Public, client-safe error details.
     *
     * @return array<string, mixed>
     */
    public function details(): array;
}
