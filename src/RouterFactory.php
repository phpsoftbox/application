<?php

declare(strict_types=1);

namespace PhpSoftBox\Application;

use PhpSoftBox\Profiler\ProfilerInterface;
use PhpSoftBox\Router\Cache\RouteCache;
use PhpSoftBox\Router\Dispatcher;
use PhpSoftBox\Router\Exception\RouteCacheException;
use PhpSoftBox\Router\Profiler\RouterProfilerCollector;
use PhpSoftBox\Router\RouteCollector;
use PhpSoftBox\Router\Router;
use PhpSoftBox\Router\RouteResolver;

final readonly class RouterFactory
{
    public function __construct(
        private Dispatcher $dispatcher,
        private ?RouteCache $cache = null,
        private ?string $environment = null,
        private ?ProfilerInterface $profiler = null,
        private ?RouterProfilerCollector $profilerCollector = null,
    ) {
    }

    /**
     * @param callable(RouteCollector):void $routes
     */
    public function create(callable $routes, ?string $environment = null): Router
    {
        $env       = $environment ?? $this->environment;
        $collector = null;

        if ($this->cache !== null) {
            try {
                $collector = $this->cache->has($env) ? $this->cache->load($env) : null;
            } catch (RouteCacheException) {
                $collector = null;
            }
        }

        if (!$collector instanceof RouteCollector) {
            $collector = new RouteCollector();

            $routes($collector);

            if ($this->cache !== null) {
                $this->cache->dump($collector, $env);
            }
        }

        return new Router(
            new RouteResolver($collector),
            $this->dispatcher,
            $collector,
            $this->profiler,
            $this->profilerCollector,
        );
    }
}
