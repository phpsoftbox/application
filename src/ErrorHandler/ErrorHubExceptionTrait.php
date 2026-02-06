<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\ErrorHandler;

use function array_filter;
use function array_merge;
use function array_unique;
use function array_values;
use function is_string;

trait ErrorHubExceptionTrait
{
    /** @var list<string> */
    private array $errorHubTags = [];
    /** @var array<string, mixed> */
    private array $errorHubContext = [];

    public function getErrorHubTags(): array
    {
        return $this->errorHubTags;
    }

    public function setErrorHubTags(array $tags): void
    {
        $clean              = array_filter($tags, static fn (mixed $tag): bool => is_string($tag) && $tag !== '');
        $this->errorHubTags = array_values(array_unique(array_merge($this->errorHubTags, $clean)));
    }

    public function getErrorHubContext(): array
    {
        return $this->errorHubContext;
    }

    public function setErrorHubContext(array $context): void
    {
        $this->errorHubContext = array_merge($this->errorHubContext, $context);
    }
}
