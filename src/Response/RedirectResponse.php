<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Response;

use PhpSoftBox\Session\SessionInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Ответ-редирект с записью flash-данных в сессию.
 */
final class RedirectResponse
{
    public function __construct(
        private readonly ResponseInterface $response,
        private readonly SessionInterface $session,
    ) {
    }

    public function withFlash(string $key, mixed $value): ResponseInterface
    {
        $this->session->flash($key, $value);

        return $this->response;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function with(array $values): ResponseInterface
    {
        foreach ($values as $key => $value) {
            $this->session->flash($key, $value);
        }

        return $this->response;
    }

    /**
     * @param array<string, mixed> $errors
     */
    public function withErrors(array $errors): ResponseInterface
    {
        $this->session->flash('errors', $errors);

        return $this->response;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function withInput(array $input): ResponseInterface
    {
        $this->session->flash('old', $input);

        return $this->response;
    }

    public function response(): ResponseInterface
    {
        return $this->response;
    }
}
