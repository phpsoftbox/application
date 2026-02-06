<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\ErrorHandler;

use Psr\Http\Message\ServerRequestInterface;

final readonly class ExceptionReportContext
{
    /**
     * @param array<string, mixed> $data
     * @param list<string> $tags
     */
    public function __construct(
        public array $data = [],
        public array $tags = [],
        public ?ServerRequestInterface $request = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $tags
     */
    public static function fromRequest(
        ServerRequestInterface $request,
        array $data = [],
        array $tags = [],
    ): self {
        return new self($data, $tags, $request);
    }
}
