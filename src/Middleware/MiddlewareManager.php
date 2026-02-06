<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Middleware;

use Psr\Http\Server\MiddlewareInterface;

use function array_merge;
use function is_string;
use function usort;

final class MiddlewareManager
{
    /** @var array<string, MiddlewareInterface|string> */
    private array $aliases = [];

    /** @var list<array{middleware: MiddlewareInterface|string, priority: int, seq: int}> */
    private array $global = [];

    /** @var array<string, list<array{middleware: MiddlewareInterface|string, priority: int, seq: int}>> */
    private array $groups = [];

    private int $sequence = 0;

    /**
     */
    public function alias(string $alias, MiddlewareInterface|string $middleware): void
    {
        $this->aliases[$alias] = $middleware;
    }

    /**
     */
    public function add(MiddlewareInterface|string $middleware, int $priority = 0): void
    {
        $this->global[] = $this->entry($middleware, $priority);
    }

    /**
     */
    public function addToGroup(string $group, MiddlewareInterface|string $middleware, int $priority = 0): void
    {
        $this->groups[$group][] = $this->entry($middleware, $priority);
    }

    /**
     * @param array<MiddlewareInterface|string> $middlewares
     */
    public function addGroup(string $group, array $middlewares, int $priority = 0): void
    {
        foreach ($middlewares as $middleware) {
            $this->addToGroup($group, $middleware, $priority);
        }
    }

    public function hasGroup(string $group): bool
    {
        return isset($this->groups[$group]) && $this->groups[$group] !== [];
    }

    /**
     * @return list<MiddlewareInterface|string>
     */
    public function groupStack(string $group): array
    {
        $entries = $this->groups[$group] ?? [];

        return $this->entriesToMiddleware($this->sortEntries($entries));
    }

    /**
     * @param string[] $groups
     * @return list<MiddlewareInterface|string>
     */
    public function stack(array $groups = []): array
    {
        $stack  = $this->sortEntries($this->global);
        $result = $this->entriesToMiddleware($stack);

        foreach ($groups as $group) {
            $entries = $this->groups[$group] ?? [];
            if ($entries === []) {
                continue;
            }

            $result = array_merge(
                $result,
                $this->entriesToMiddleware($this->sortEntries($entries)),
            );
        }

        return $result;
    }

    /**
     */
    public function resolveAlias(MiddlewareInterface|string $middleware): MiddlewareInterface|string
    {
        if (!is_string($middleware)) {
            return $middleware;
        }

        $visited = [];

        while (isset($this->aliases[$middleware])) {
            if (isset($visited[$middleware])) {
                break;
            }

            $visited[$middleware] = true;
            $middleware           = $this->aliases[$middleware];

            if (!is_string($middleware)) {
                return $middleware;
            }
        }

        return $middleware;
    }

    /**
     * @return array{middleware: MiddlewareInterface|string, priority: int, seq: int}
     */
    private function entry(MiddlewareInterface|string $middleware, int $priority): array
    {
        return [
            'middleware' => $middleware,
            'priority'   => $priority,
            'seq'        => $this->sequence++,
        ];
    }

    /**
     * @param list<array{middleware: MiddlewareInterface|string, priority: int, seq: int}> $entries
     * @return list<array{middleware: MiddlewareInterface|string, priority: int, seq: int}>
     */
    private function sortEntries(array $entries): array
    {
        if ($entries === []) {
            return [];
        }

        usort($entries, static function (array $left, array $right): int {
            if ($left['priority'] === $right['priority']) {
                return $left['seq'] <=> $right['seq'];
            }

            return $right['priority'] <=> $left['priority'];
        });

        return $entries;
    }

    /**
     * @param list<array{middleware: MiddlewareInterface|string, priority: int, seq: int}> $entries
     * @return list<MiddlewareInterface|string>
     */
    private function entriesToMiddleware(array $entries): array
    {
        $result = [];
        foreach ($entries as $entry) {
            $result[] = $entry['middleware'];
        }

        return $result;
    }
}
