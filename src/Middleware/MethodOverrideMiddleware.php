<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function in_array;
use function is_array;
use function is_string;
use function strtoupper;

final class MethodOverrideMiddleware implements MiddlewareInterface
{
    /**
     * @param list<string> $allowedMethods
     */
    public function __construct(
        private readonly array $allowedMethods = ['PUT', 'PATCH', 'DELETE'],
        private readonly string $header = 'X-HTTP-Method-Override',
        private readonly string $field = '_method',
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (strtoupper($request->getMethod()) !== 'POST') {
            return $handler->handle($request);
        }

        $override = $request->getHeaderLine($this->header);
        if ($override === '') {
            $body = $request->getParsedBody();
            if (is_array($body) && isset($body[$this->field]) && is_string($body[$this->field])) {
                $override = $body[$this->field];
            }
        }

        if ($override !== '') {
            $override = strtoupper($override);
            if (in_array($override, $this->allowedMethods, true)) {
                $request = $request->withMethod($override);
            }
        }

        return $handler->handle($request);
    }
}
