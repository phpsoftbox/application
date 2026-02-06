<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Contracts;

interface EnvironmentPolicyInterface
{
    public function isDebugAvailableFor(EnvironmentEnumInterface $environment): bool;

    public function isProductionLike(EnvironmentEnumInterface $environment): bool;
}
