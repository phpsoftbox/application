<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\ErrorHandler;

use PhpSoftBox\Application\ErrorHandler\Mapper\RouteExceptionMapper;
use PhpSoftBox\Http\Message\Redirector;
use PhpSoftBox\Session\SessionInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

use function array_key_exists;
use function class_exists;
use function is_a;
use function is_array;
use function is_callable;
use function is_object;
use function is_string;
use function str_contains;

final class DefaultExceptionHandler implements ExceptionHandlerInterface
{
    /**
     * @param list<ExceptionReporterInterface|callable> $reporters
     * @param list<string> $dontReport
     * @param list<string> $dontFlash
     */
    public function __construct(
        private readonly ExceptionHandlerInterface $fallbackHandler,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly SessionInterface $session,
        private readonly array $reporters = [],
        private readonly array $dontReport = [],
        private readonly array $dontFlash = [],
        private readonly ?ExceptionMapperRegistry $mapperRegistry = null,
    ) {
    }

    public function handle(Throwable $exception, ServerRequestInterface $request): ResponseInterface
    {
        $this->report($exception, $request);

        if ($this->isValidationException($exception)) {
            return $this->handleValidationException($exception, $request);
        }

        if ($this->isCsrfMismatch($exception)) {
            return $this->handleCsrfMismatch($request);
        }

        return $this->fallbackHandler->handle($this->normalizeException($exception), $request);
    }

    private function report(Throwable $exception, ServerRequestInterface $request): void
    {
        if (!$this->shouldReport($exception)) {
            return;
        }

        foreach ($this->reporters as $reporter) {
            try {
                if ($reporter instanceof ExceptionReporterInterface) {
                    $reporter->report($exception, ExceptionReportContext::fromRequest($request));
                    continue;
                }

                if (is_object($reporter) && is_callable($reporter)) {
                    $reporter($exception, $request);
                    continue;
                }

                if (is_callable($reporter)) {
                    $reporter($exception, $request);
                }
            } catch (Throwable) {
                // Reporting errors must not replace the original application exception.
            }
        }
    }

    private function shouldReport(Throwable $exception): bool
    {
        foreach ($this->dontReport as $class) {
            if (!is_string($class) || $class === '') {
                continue;
            }

            if (is_a($exception, $class)) {
                return false;
            }
        }

        return true;
    }

    private function handleValidationException(Throwable $exception, ServerRequestInterface $request): ResponseInterface
    {
        if ($this->shouldReturnJson($request)) {
            return $this->fallbackHandler->handle($exception, $request);
        }

        $this->startSession();

        $errors  = $this->normalizeErrors($exception->errors());
        $message = $this->firstError($errors);
        $input   = $this->filterInput($request->getParsedBody());

        $redirector = new Redirector($this->responseFactory, $this->session, $request);

        $response = $redirector->back();
        $response->withErrors($errors);

        if ($input !== []) {
            $response->withInput($input);
        }

        $this->session->save();

        return $response->response();
    }

    private function handleCsrfMismatch(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->shouldReturnJson($request)) {
            return $this->fallbackHandler->handle(new RuntimeException('CSRF token mismatch.'), $request);
        }

        $this->startSession();

        $redirector = new Redirector($this->responseFactory, $this->session, $request);

        $response = $redirector->back();
        $response->withFlash('danger', 'Сессия устарела. Обновите страницу и попробуйте ещё раз.');

        $this->session->save();

        return $response->response();
    }

    private function startSession(): void
    {
        if (!$this->session->isStarted()) {
            $this->session->start();
        }
    }

    /**
     * @param array<string, list<string>> $errors
     * @return array<string, string>
     */
    private function normalizeErrors(array $errors): array
    {
        $result = [];
        foreach ($errors as $field => $messages) {
            if ($messages === []) {
                continue;
            }
            $result[$field] = (string) $messages[0];
        }

        return $result;
    }

    /**
     * @param array<string, string> $errors
     */
    private function firstError(array $errors): ?string
    {
        foreach ($errors as $message) {
            if ($message !== '') {
                return $message;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function filterInput(mixed $body): array
    {
        if (!is_array($body)) {
            return [];
        }

        $filtered = $body;

        foreach ($this->dontFlash as $key) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            if (array_key_exists($key, $filtered)) {
                unset($filtered[$key]);
            }
        }

        return $filtered;
    }

    private function shouldReturnJson(ServerRequestInterface $request): bool
    {
        if ($request->getHeaderLine('X-Inertia') !== '') {
            return false;
        }

        $accept = $request->getHeaderLine('Accept');
        if ($accept === '') {
            return false;
        }

        return str_contains($accept, 'application/json');
    }

    private function isValidationException(Throwable $exception): bool
    {
        if (!class_exists('PhpSoftBox\\Validator\\Exception\\ValidationException')) {
            return false;
        }

        return is_a($exception, 'PhpSoftBox\\Validator\\Exception\\ValidationException');
    }

    private function isCsrfMismatch(Throwable $exception): bool
    {
        if (!class_exists('PhpSoftBox\\Session\\Exception\\CsrfTokenMismatchException')) {
            return false;
        }

        return is_a($exception, 'PhpSoftBox\\Session\\Exception\\CsrfTokenMismatchException');
    }

    private function normalizeException(Throwable $exception): Throwable
    {
        $mapperRegistry = $this->mapperRegistry ?? new ExceptionMapperRegistry([
            new RouteExceptionMapper(),
        ]);

        return $mapperRegistry->map($exception);
    }
}
