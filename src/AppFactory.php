<?php

declare(strict_types=1);

namespace PhpSoftBox\Application;

use PhpSoftBox\Application\Middleware\KernelRouteMiddlewareResolver;
use PhpSoftBox\Application\Middleware\MiddlewareManager;
use PhpSoftBox\Http\Emitter\EmitterInterface;
use PhpSoftBox\Http\Message\ServerRequestCreator;
use PhpSoftBox\Profiler\ProfilerInterface;
use PhpSoftBox\Router\Cache\RouteCache;
use PhpSoftBox\Router\Dispatcher;
use PhpSoftBox\Router\Exception\RouteCacheException;
use PhpSoftBox\Router\Handler\ContainerHandlerResolver;
use PhpSoftBox\Router\Handler\HandlerResolverInterface;
use PhpSoftBox\Router\Middleware\RouteMiddlewareResolverInterface;
use PhpSoftBox\Router\Profiler\RouterProfilerCollector;
use PhpSoftBox\Router\RouteCollector;
use PhpSoftBox\Router\RouteCollectorFactory;
use PhpSoftBox\Router\RouteCollectorFactoryInterface;
use PhpSoftBox\Router\Router;
use PhpSoftBox\Router\RouteResolver;
use Psr\Container\ContainerInterface;
use Throwable;

final class AppFactory
{
    public static function createFromContainer(
        ContainerInterface $container,
        ?RouteCache $routeCache = null,
        ?string $environment = null,
        ?RouteCollectorFactoryInterface $routesFactory = null,
        array|string|null $routesPath = null,
    ): Application {
        $middlewareManager  = self::resolveMiddlewareManager($container);
        $handlerResolver    = self::resolveHandlerResolver($container);
        $middlewareResolver = self::resolveMiddlewareResolver($container, $middlewareManager);
        $dispatcher         = self::resolveDispatcher($container, $handlerResolver, $middlewareResolver);

        $routeCache ??= self::resolveRouteCache($container);

        if ($routesFactory === null && $routesPath !== null) {
            $routesFactory = new RouteCollectorFactory($routesPath);
        }

        [$router, $routesCached] = self::resolveRouter($container, $dispatcher, $routeCache, $environment, $routesFactory);

        $requestCreator = $container->has(ServerRequestCreator::class)
            ? $container->get(ServerRequestCreator::class)
            : null;
        $emitter = $container->has(EmitterInterface::class)
            ? $container->get(EmitterInterface::class)
            : null;

        $app = new Application(
            $router,
            $middlewareManager,
            $container,
            $requestCreator,
            $emitter,
            $routesCached,
            $routesPath,
        );

        return $app;
    }

    private static function resolveMiddlewareManager(ContainerInterface $container): MiddlewareManager
    {
        if ($container->has(MiddlewareManager::class)) {
            return $container->get(MiddlewareManager::class);
        }

        return new MiddlewareManager();
    }

    private static function resolveHandlerResolver(ContainerInterface $container): HandlerResolverInterface
    {
        if ($container->has(HandlerResolverInterface::class)) {
            return $container->get(HandlerResolverInterface::class);
        }

        return new ContainerHandlerResolver($container);
    }

    private static function resolveMiddlewareResolver(
        ContainerInterface $container,
        MiddlewareManager $manager,
    ): RouteMiddlewareResolverInterface {
        if ($container->has(RouteMiddlewareResolverInterface::class)) {
            return $container->get(RouteMiddlewareResolverInterface::class);
        }

        return new KernelRouteMiddlewareResolver($manager, $container);
    }

    private static function resolveDispatcher(
        ContainerInterface $container,
        HandlerResolverInterface $handlerResolver,
        RouteMiddlewareResolverInterface $middlewareResolver,
    ): Dispatcher {
        if ($container->has(Dispatcher::class)) {
            return $container->get(Dispatcher::class);
        }

        return new Dispatcher($handlerResolver, $middlewareResolver);
    }

    /**
     * @return array{Router, bool}
     */
    private static function resolveRouter(
        ContainerInterface $container,
        Dispatcher $dispatcher,
        ?RouteCache $routeCache,
        ?string $environment,
        ?RouteCollectorFactoryInterface $routesFactory,
    ): array {
        $routesCached = false;
        $collector    = null;

        if ($routeCache !== null) {
            try {
                $collector    = $routeCache->has($environment) ? $routeCache->load($environment) : null;
                $routesCached = $collector instanceof RouteCollector;
            } catch (RouteCacheException) {
            }
        }

        if (!$collector instanceof RouteCollector) {
            $collector = $routesFactory?->create() ?? new RouteCollector();
        }

        $profiler = $container->has(ProfilerInterface::class)
            ? $container->get(ProfilerInterface::class)
            : null;
        $routerProfilerCollector = $container->has(RouterProfilerCollector::class)
            ? $container->get(RouterProfilerCollector::class)
            : null;

        $router = new Router(
            new RouteResolver($collector),
            $dispatcher,
            $collector,
            $profiler instanceof ProfilerInterface ? $profiler : null,
            $routerProfilerCollector instanceof RouterProfilerCollector ? $routerProfilerCollector : null,
        );

        return [$router, $routesCached];
    }

    private static function resolveRouteCache(ContainerInterface $container): ?RouteCache
    {
        if (!$container->has(RouteCache::class)) {
            return null;
        }

        try {
            $cache = $container->get(RouteCache::class);
        } catch (Throwable) {
            return null;
        }

        return $cache instanceof RouteCache ? $cache : null;
    }
}
