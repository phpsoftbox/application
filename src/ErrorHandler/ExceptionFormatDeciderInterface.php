<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\ErrorHandler;

use Psr\Http\Message\ServerRequestInterface;
use Throwable;

interface ExceptionFormatDeciderInterface
{
    public function __invoke(Throwable $exception, ServerRequestInterface $request): ?ExceptionFormat;
}
