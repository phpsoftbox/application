<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\ErrorHandler;

use PhpSoftBox\Application\Exception\CodedHttpExceptionInterface;
use PhpSoftBox\ErrorFormatter\ThrowableFormatter;
use PhpSoftBox\Validator\Exception\ValidationException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Throwable;

use function is_array;
use function json_encode;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final class JsonExceptionHandler extends AbstractExceptionHandler
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        bool $includeDetails = false,
    ) {
        parent::__construct($includeDetails);
    }

    public function handle(Throwable $exception, ServerRequestInterface $request): ResponseInterface
    {
        ['status' => $status, 'headers' => $headers] = $this->resolveStatusAndHeaders($exception);

        $payload = [
            'code'    => $this->resolveErrorCode($exception, $status),
            'title'   => $this->resolveTitle($exception, $status),
            'message' => $this->resolveMessage($exception, $status),
        ];

        if ($exception instanceof CodedHttpExceptionInterface && $exception->details() !== []) {
            $payload['details'] = $exception->details();
        }

        if ($exception instanceof ValidationException) {
            $payload['errors'] = $exception->errors();
        }

        if ($this->includeDetails) {
            $payload['debug_message'] = $this->resolveDebugMessage($exception);
            $payload['exception']     = $exception::class;
            $payload['file']          = $exception->getFile();
            $payload['line']          = $exception->getLine();
            $payload['trace']         = ThrowableFormatter::toTrace($exception);
        }

        $body   = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stream = $this->streamFactory->createStream($body === false ? '' : $body);

        $response = $this->responseFactory->createResponse($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, is_array($value) ? $value : (string) $value);
        }

        return $response->withBody($stream);
    }
}
