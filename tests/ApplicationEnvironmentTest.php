<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Tests;

use InvalidArgumentException;
use PhpSoftBox\Application\ApplicationEnvironment;
use PhpSoftBox\Application\Contracts\EnvironmentEnumInterface;
use PhpSoftBox\Application\Contracts\EnvironmentPolicyInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApplicationEnvironment::class)]
final class ApplicationEnvironmentTest extends TestCase
{
    #[Test]
    public function exposesAndComparesCurrentEnvironment(): void
    {
        $environment = new ApplicationEnvironment(TestEnvironment::DEMO);

        self::assertSame(TestEnvironment::DEMO, $environment->current());
        self::assertSame('demo', $environment->value());
        self::assertTrue($environment->is(TestEnvironment::DEV, TestEnvironment::DEMO));
        self::assertFalse($environment->is(TestEnvironment::PROD));
    }

    #[Test]
    public function rejectsEnvironmentFromAnotherEnum(): void
    {
        $environment = new ApplicationEnvironment(TestEnvironment::DEV);

        $this->expectException(InvalidArgumentException::class);

        $environment->is(OtherTestEnvironment::DEV);
    }

    #[Test]
    public function rejectsIntBackedEnvironment(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ApplicationEnvironment(IntTestEnvironment::DEV);
    }

    #[Test]
    public function appliesPolicyToDebugAndProductionLikeChecks(): void
    {
        $policy = new TestEnvironmentPolicy();

        $demo = new ApplicationEnvironment(TestEnvironment::DEMO, $policy, debugRequested: true);

        self::assertTrue($demo->isDebug());
        self::assertFalse($demo->isProductionLike());

        $prod = new ApplicationEnvironment(TestEnvironment::PROD, $policy, debugRequested: true);

        self::assertFalse($prod->isDebug());
        self::assertTrue($prod->isProductionLike());
    }

    #[Test]
    public function deniesPolicyChecksWithoutPolicy(): void
    {
        $environment = new ApplicationEnvironment(TestEnvironment::DEV, debugRequested: true);

        self::assertFalse($environment->isDebug());
        self::assertFalse($environment->isProductionLike());
    }
}

enum TestEnvironment: string implements EnvironmentEnumInterface
{
    case DEV  = 'dev';
    case DEMO = 'demo';
    case PROD = 'prod';
}

enum OtherTestEnvironment: string implements EnvironmentEnumInterface
{
    case DEV = 'dev';
}

enum IntTestEnvironment: int implements EnvironmentEnumInterface
{
    case DEV = 1;
}

final readonly class TestEnvironmentPolicy implements EnvironmentPolicyInterface
{
    public function isDebugAvailableFor(EnvironmentEnumInterface $environment): bool
    {
        return $environment === TestEnvironment::DEV || $environment === TestEnvironment::DEMO;
    }

    public function isProductionLike(EnvironmentEnumInterface $environment): bool
    {
        return $environment === TestEnvironment::PROD;
    }
}
