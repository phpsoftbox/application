<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\ErrorHandler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

interface ExceptionHandlerInterface
{
    public function handle(Throwable $exception, ServerRequestInterface $request): ResponseInterface;
}
