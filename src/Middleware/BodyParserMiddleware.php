<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Middleware;

use PhpSoftBox\Application\Exception\BadRequestHttpException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function explode;
use function json_decode;
use function json_last_error;
use function parse_str;
use function str_contains;
use function str_ends_with;
use function strtolower;
use function trim;

use const JSON_ERROR_NONE;

final class BodyParserMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly bool $parseJson = true,
        private readonly bool $parseForm = true,
        private readonly bool $throwOnError = true,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getParsedBody() !== null) {
            return $handler->handle($request);
        }

        $contentType = strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'))[0] ?? ''));
        if ($contentType === '') {
            return $handler->handle($request);
        }

        $body = $request->getBody();
        $raw  = (string) $body;
        if ($raw === '') {
            return $handler->handle($request);
        }

        if ($body->isSeekable()) {
            $body->rewind();
        }

        $parsed = null;

        if ($this->parseJson && ($contentType === 'application/json' || str_ends_with($contentType, '+json') || str_contains($contentType, '/json'))) {
            $parsed = json_decode($raw, true);
            if ($parsed === null && json_last_error() !== JSON_ERROR_NONE) {
                if ($this->throwOnError) {
                    throw new BadRequestHttpException('Некорректный JSON в теле запроса.');
                }

                return $handler->handle($request);
            }
        }

        if ($parsed === null && $this->parseForm && $contentType === 'application/x-www-form-urlencoded') {
            $parsed = [];
            parse_str($raw, $parsed);
        }

        if ($parsed === null) {
            return $handler->handle($request);
        }

        return $handler->handle($request->withParsedBody($parsed));
    }
}
