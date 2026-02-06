<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\ErrorHandler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

use function str_contains;
use function strtolower;

final class ContentNegotiationExceptionHandler implements ExceptionHandlerInterface
{
    public function __construct(
        private readonly ExceptionHandlerInterface $jsonHandler,
        private readonly ExceptionHandlerInterface $htmlHandler,
        private readonly ?ExceptionFormatDeciderRegistry $deciders = null,
    ) {
    }

    public function handle(Throwable $exception, ServerRequestInterface $request): ResponseInterface
    {
        return $this->resolveHandler($exception, $request)->handle($exception, $request);
    }

    private function resolveHandler(Throwable $exception, ServerRequestInterface $request): ExceptionHandlerInterface
    {
        $format = $this->deciders?->decide($exception, $request);
        if ($format === ExceptionFormat::JSON) {
            return $this->jsonHandler;
        }
        if ($format === ExceptionFormat::HTML) {
            return $this->htmlHandler;
        }

        return $this->wantsJson($request) ? $this->jsonHandler : $this->htmlHandler;
    }

    private function wantsJson(ServerRequestInterface $request): bool
    {
        if (strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest') {
            return true;
        }

        $accept = strtolower($request->getHeaderLine('Accept'));

        if ($accept === '') {
            return false;
        }

        return str_contains($accept, 'application/json')
            || str_contains($accept, '+json')
            || str_contains($accept, '/json');
    }
}
