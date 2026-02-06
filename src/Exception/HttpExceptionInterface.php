<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Exception;

use Throwable;

interface HttpExceptionInterface extends Throwable
{
    /**
     * @return array<string, string|string[]>
     */
    public function headers(): array;

    public function statusCode(): int;

    public function message(): string;

    public function title(): ?string;

    public function debugMessage(): ?string;
}
