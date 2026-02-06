<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\ErrorHandler;

use Throwable;

use function class_exists;
use function is_string;
use function trim;

final class ExceptionMapperRegistry
{
    /** @var list<ExceptionMapperInterface> */
    private array $mappers = [];

    /**
     * @param list<ExceptionMapperInterface|class-string<ExceptionMapperInterface>> $mappers
     */
    public function __construct(array $mappers = [])
    {
        foreach ($mappers as $mapper) {
            if ($mapper instanceof ExceptionMapperInterface) {
                $this->mappers[] = $mapper;
                continue;
            }

            if (!is_string($mapper) || trim($mapper) === '' || !class_exists($mapper)) {
                continue;
            }

            $instance = new $mapper();

            if ($instance instanceof ExceptionMapperInterface) {
                $this->mappers[] = $instance;
            }
        }
    }

    public function register(ExceptionMapperInterface $mapper): void
    {
        $this->mappers[] = $mapper;
    }

    public function map(Throwable $exception): Throwable
    {
        foreach ($this->mappers as $mapper) {
            $mapped = $mapper->map($exception);
            if ($mapped instanceof Throwable) {
                return $mapped;
            }
        }

        return $exception;
    }
}
