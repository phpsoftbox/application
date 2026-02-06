<?php

declare(strict_types=1);

namespace PhpSoftBox\Application;

use InvalidArgumentException;
use PhpSoftBox\Application\Contracts\EnvironmentEnumInterface;
use PhpSoftBox\Application\Contracts\EnvironmentPolicyInterface;

use function is_string;

final readonly class ApplicationEnvironment
{
    public function __construct(
        private EnvironmentEnumInterface $current,
        private ?EnvironmentPolicyInterface $policy = null,
        private bool $debugRequested = false,
    ) {
        if (!is_string($this->current->value)) {
            throw new InvalidArgumentException('Application environment must be a string-backed enum.');
        }
    }

    public function current(): EnvironmentEnumInterface
    {
        return $this->current;
    }

    public function value(): string
    {
        return $this->current->value;
    }

    public function is(EnvironmentEnumInterface ...$environments): bool
    {
        foreach ($environments as $environment) {
            if ($environment::class !== $this->current::class) {
                throw new InvalidArgumentException('Cannot compare environments from different enum classes.');
            }

            if ($environment === $this->current) {
                return true;
            }
        }

        return false;
    }

    public function isDebug(): bool
    {
        return $this->debugRequested
            && $this->policy?->isDebugAvailableFor($this->current) === true;
    }

    public function isProductionLike(): bool
    {
        return $this->policy?->isProductionLike($this->current) === true;
    }
}
