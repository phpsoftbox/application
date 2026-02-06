<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Tests;

use PhpSoftBox\Application\Response\HtmlResponse;
use PhpSoftBox\Application\Response\JsonResponse;
use PhpSoftBox\Application\Response\TextResponse;
use PhpSoftBox\Application\Response\XmlResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonResponse::class)]
#[CoversClass(HtmlResponse::class)]
#[CoversClass(XmlResponse::class)]
#[CoversClass(TextResponse::class)]
final class ResponseTest extends TestCase
{
    /**
     * Проверяет выставление Content-Type и тела для JsonResponse.
     */
    #[Test]
    public function testJsonResponse(): void
    {
        $response = new JsonResponse(['ok' => true]);

        $this->assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertSame('{"ok":true}', (string) $response->getBody());
    }

    /**
     * Проверяет выставление Content-Type для HtmlResponse.
     */
    #[Test]
    public function testHtmlResponse(): void
    {
        $response = new HtmlResponse('<p>ok</p>');

        $this->assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
    }

    /**
     * Проверяет выставление Content-Type для XmlResponse.
     */
    #[Test]
    public function testXmlResponse(): void
    {
        $response = new XmlResponse('<xml></xml>');

        $this->assertSame('application/xml; charset=utf-8', $response->getHeaderLine('Content-Type'));
    }

    /**
     * Проверяет выставление Content-Type для TextResponse.
     */
    #[Test]
    public function testTextResponse(): void
    {
        $response = new TextResponse('ok');

        $this->assertSame('text/plain; charset=utf-8', $response->getHeaderLine('Content-Type'));
    }
}
