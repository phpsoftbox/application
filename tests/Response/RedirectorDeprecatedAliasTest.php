<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Tests\Response;

use PhpSoftBox\Application\Response\Redirector;
use PhpSoftBox\Application\Response\RedirectResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function class_exists;
use function is_a;

/**
 * Старые имена из phpsoftbox/http-message остаются рабочими, пока проекты не заменят импорты.
 */
#[CoversClass(Redirector::class)]
#[CoversClass(RedirectResponse::class)]
final class RedirectorDeprecatedAliasTest extends TestCase
{
    /**
     * Проверим, что старые имена классов указывают на классы Application.
     *
     * @see Redirector
     * @see RedirectResponse
     */
    #[Test]
    public function oldHttpMessageNamesAreAliases(): void
    {
        self::assertTrue(class_exists('PhpSoftBox\Http\Message\Redirector'));
        self::assertTrue(is_a('PhpSoftBox\Http\Message\Redirector', Redirector::class, true));
        self::assertTrue(is_a('PhpSoftBox\Http\Message\RedirectResponse', RedirectResponse::class, true));
    }
}
