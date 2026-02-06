<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Tests;

use PhpSoftBox\Application\ErrorHandler\ContentNegotiationExceptionHandler;
use PhpSoftBox\Application\ErrorHandler\ExceptionFormat;
use PhpSoftBox\Application\ErrorHandler\ExceptionFormatDeciderInterface;
use PhpSoftBox\Application\ErrorHandler\ExceptionFormatDeciderRegistry;
use PhpSoftBox\Application\ErrorHandler\HtmlExceptionHandler;
use PhpSoftBox\Application\ErrorHandler\JsonExceptionHandler;
use PhpSoftBox\Http\Message\ResponseFactory;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Http\Message\StreamFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

final class ContentNegotiationExceptionHandlerTest extends TestCase
{
    /**
     * Проверяем выбор JSON-ответа при Accept: application/json.
     */
    public function testJsonAccept(): void
    {
        $handler = $this->makeHandler();
        $request = new ServerRequest('GET', 'https://example.com/')
            ->withHeader('Accept', 'application/json');

        $response = $handler->handle(new RuntimeException('Oops'), $request);

        $this->assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
    }

    /**
     * Проверяем выбор JSON-ответа при X-Requested-With: XMLHttpRequest.
     */
    public function testJsonXmlHttpRequestHeader(): void
    {
        $handler = $this->makeHandler();
        $request = new ServerRequest('GET', 'https://example.com/')
            ->withHeader('X-Requested-With', 'XMLHttpRequest');

        $response = $handler->handle(new RuntimeException('Oops'), $request);

        $this->assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
    }

    /**
     * Проверяем, что deciders могут переопределить выбор обработчика.
     */
    public function testDecidersOverride(): void
    {
        $handler = $this->makeHandlerWithDeciders([
            new class () implements ExceptionFormatDeciderInterface {
                public function __invoke(Throwable $exception, ServerRequestInterface $request): ?ExceptionFormat
                {
                    return ExceptionFormat::HTML;
                }
            },
        ]);
        $request = new ServerRequest('GET', 'https://example.com/')
            ->withHeader('Accept', 'application/json');

        $response = $handler->handle(new RuntimeException('Oops'), $request);

        $this->assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
    }

    /**
     * Проверяем выбор HTML-ответа по умолчанию.
     */
    public function testHtmlDefault(): void
    {
        $handler = $this->makeHandler();
        $request = new ServerRequest('GET', 'https://example.com/');

        $response = $handler->handle(new RuntimeException('Oops'), $request);

        $this->assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
    }

    private function makeHandler(): ContentNegotiationExceptionHandler
    {
        $responseFactory = new ResponseFactory();
        $streamFactory   = new StreamFactory();

        return new ContentNegotiationExceptionHandler(
            new JsonExceptionHandler($responseFactory, $streamFactory, includeDetails: false),
            new HtmlExceptionHandler($responseFactory, $streamFactory, includeDetails: false),
        );
    }

    /**
     * @param list<ExceptionFormatDeciderInterface> $deciders
     */
    private function makeHandlerWithDeciders(array $deciders): ContentNegotiationExceptionHandler
    {
        $responseFactory = new ResponseFactory();
        $streamFactory   = new StreamFactory();

        $registry = new ExceptionFormatDeciderRegistry($deciders);

        return new ContentNegotiationExceptionHandler(
            new JsonExceptionHandler($responseFactory, $streamFactory, includeDetails: false),
            new HtmlExceptionHandler($responseFactory, $streamFactory, includeDetails: false),
            $registry,
        );
    }
}
