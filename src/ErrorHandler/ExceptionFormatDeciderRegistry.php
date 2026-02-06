<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\ErrorHandler;

use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class ExceptionFormatDeciderRegistry
{
    /** @var list<ExceptionFormatDeciderInterface> */
    private array $deciders = [];

    /**
     * @param list<ExceptionFormatDeciderInterface> $deciders
     */
    public function __construct(array $deciders = [])
    {
        foreach ($deciders as $decider) {
            $this->register($decider);
        }
    }

    public function register(ExceptionFormatDeciderInterface $decider): void
    {
        $this->deciders[] = $decider;
    }

    public function decide(Throwable $exception, ServerRequestInterface $request): ?ExceptionFormat
    {
        foreach ($this->deciders as $decider) {
            try {
                $format = $decider($exception, $request);
            } catch (Throwable) {
                continue;
            }

            if ($format instanceof ExceptionFormat) {
                return $format;
            }
        }

        return null;
    }
}
