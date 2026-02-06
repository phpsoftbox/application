<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\ErrorHandler;

interface ErrorHubExceptionInterface
{
    /**
     * @return list<string>
     */
    public function getErrorHubTags(): array;

    /**
     * @param list<string> $tags
     */
    public function setErrorHubTags(array $tags): void;

    /**
     * @return array<string, mixed>
     */
    public function getErrorHubContext(): array;

    /**
     * @param array<string, mixed> $context
     */
    public function setErrorHubContext(array $context): void;
}
