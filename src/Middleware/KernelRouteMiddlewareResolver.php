<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Middleware;

use InvalidArgumentException;
use PhpSoftBox\Router\Middleware\RouteMiddlewareResolverInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;

use function get_debug_type;
use function is_string;

final class KernelRouteMiddlewareResolver implements RouteMiddlewareResolverInterface
{
    public function __construct(
        private readonly MiddlewareManager $manager,
        private readonly ?ContainerInterface $container = null,
    ) {
    }

    /**
     * @param array<MiddlewareInterface|string> $middlewares
     * @return list<MiddlewareInterface>
     */
    public function resolve(array $middlewares): array
    {
        $resolved = [];

        foreach ($middlewares as $middleware) {
            foreach ($this->resolveOne($middleware, []) as $item) {
                $resolved[] = $item;
            }
        }

        return $resolved;
    }

    /**
     * @param array<string, bool> $visitedGroups
     * @return list<MiddlewareInterface>
     */
    private function resolveOne(MiddlewareInterface|string $middleware, array $visitedGroups): array
    {
        $middleware = $this->manager->resolveAlias($middleware);

        if ($middleware instanceof MiddlewareInterface) {
            return [$middleware];
        }

        if (is_string($middleware)) {
            if ($this->manager->hasGroup($middleware)) {
                if (isset($visitedGroups[$middleware])) {
                    return [];
                }

                $visitedGroups[$middleware] = true;

                $resolved = [];
                foreach ($this->manager->groupStack($middleware) as $entry) {
                    foreach ($this->resolveOne($entry, $visitedGroups) as $item) {
                        $resolved[] = $item;
                    }
                }

                return $resolved;
            }

            return [$this->instantiate($middleware)];
        }

        $type = get_debug_type($middleware);

        throw new InvalidArgumentException("Unsupported middleware definition: {$type}");
    }

    private function instantiate(string $middleware): MiddlewareInterface
    {
        if ($this->container !== null) {
            $instance = $this->container->get($middleware);
        } else {
            $instance = new $middleware();
        }

        if (!$instance instanceof MiddlewareInterface) {
            throw new InvalidArgumentException("Resolved middleware must implement MiddlewareInterface: {$middleware}");
        }

        return $instance;
    }
}
