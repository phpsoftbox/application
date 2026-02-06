<?php

declare(strict_types=1);

namespace PhpSoftBox\Application;

use BadMethodCallException;
use InvalidArgumentException;
use PhpSoftBox\Application\ErrorHandler\ExceptionHandlerInterface;
use PhpSoftBox\Application\Middleware\ErrorHandlerMiddleware;
use PhpSoftBox\Application\Middleware\MiddlewareManager;
use PhpSoftBox\Http\Emitter\EmitterInterface;
use PhpSoftBox\Http\Emitter\SapiEmitter;
use PhpSoftBox\Http\Message\ServerRequestCreator;
use PhpSoftBox\Router\ResourceBuilder;
use PhpSoftBox\Router\RouteBuilder;
use PhpSoftBox\Router\RouteCollector;
use PhpSoftBox\Router\Router;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

use function array_reverse;
use function get_debug_type;
use function is_array;
use function is_callable;
use function is_dir;
use function is_file;
use function is_string;
use function rtrim;
use function sort;

use const SORT_STRING;

final class Application implements RequestHandlerInterface
{
    private MiddlewareManager $middlewareManager;
    private ?ContainerInterface $container;
    private array $middlewareGroups = [];
    private ?RouteCollector $routes = null;
    private ?ServerRequestCreator $requestCreator;
    private ?EmitterInterface $emitter;
    private bool $routesCached;
    /**
     * @var string[]
     */
    private array $routesPaths = [];

    public function __construct(
        private RequestHandlerInterface $handler,
        array|MiddlewareManager $middlewares = [],
        ?ContainerInterface $container = null,
        ?ServerRequestCreator $requestCreator = null,
        ?EmitterInterface $emitter = null,
        bool $routesCached = false,
        array|string|null $routesPaths = null,
    ) {
        $this->middlewareManager = $middlewares instanceof MiddlewareManager
            ? $middlewares
            : new MiddlewareManager();

        if (is_array($middlewares)) {
            foreach ($middlewares as $middleware) {
                $this->middlewareManager->add($middleware);
            }
        }

        $this->container      = $container;
        $this->routes         = $handler instanceof Router ? $handler->routes() : null;
        $this->requestCreator = $requestCreator;
        $this->emitter        = $emitter;
        $this->routesCached   = $routesCached;
        $this->routesPaths    = $this->normalizeRoutesPaths($routesPaths);
    }

    /**
     */
    public function add(MiddlewareInterface|string $middleware, int $priority = 0): self
    {
        $this->middlewareManager->add($middleware, $priority);

        return $this;
    }

    /**
     */
    public function addMiddlewareToGroup(string $group, MiddlewareInterface|string $middleware, int $priority = 0): self
    {
        $this->middlewareManager->addToGroup($group, $middleware, $priority);

        return $this;
    }

    /**
     * @param array<MiddlewareInterface|string> $middlewares
     */
    public function middlewareGroup(string $name, array $middlewares, int $priority = 0): self
    {
        $this->middlewareManager->addGroup($name, $middlewares, $priority);

        return $this;
    }

    /**
     */
    public function alias(string $alias, MiddlewareInterface|string $middleware): self
    {
        $this->middlewareManager->alias($alias, $middleware);

        return $this;
    }

    public function registerMiddleware(callable $configurator): self
    {
        $configurator($this);

        return $this;
    }

    public function registerMiddlewareFromFile(string $path): self
    {
        if (!is_file($path)) {
            return $this;
        }

        $config = require $path;

        if (is_callable($config)) {
            $config($this);

            return $this;
        }

        if (is_array($config)) {
            $this->applyMiddlewareConfig($config);
        }

        return $this;
    }

    public function registerRoutesFromPath(array|string|null $paths = null): self
    {
        if ($this->routesCached) {
            return $this;
        }

        $paths = $paths ?? $this->routesPaths;
        $paths = $this->normalizeRoutesPaths($paths);

        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            foreach ($this->collectRouteFiles($path) as $routeFile) {
                $routes = require $routeFile;
                if (is_callable($routes)) {
                    $routes($this->routes());
                }
            }
        }

        return $this;
    }

    public function registerErrorHandlerMiddleware(int $priority = 100): self
    {
        if ($this->container === null) {
            throw new RuntimeException('Container is required to register error handler middleware.');
        }

        if (!$this->container->has(ExceptionHandlerInterface::class)) {
            throw new RuntimeException('ExceptionHandlerInterface is not bound in container.');
        }

        try {
            $handler = $this->container->get(ExceptionHandlerInterface::class);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'ExceptionHandlerInterface cannot be resolved from container.',
                0,
                $exception,
            );
        }

        if (!$handler instanceof ExceptionHandlerInterface) {
            $type = get_debug_type($handler);

            throw new RuntimeException("ExceptionHandlerInterface resolved to invalid type: {$type}");
        }

        $this->add(ErrorHandlerMiddleware::class, $priority);

        return $this;
    }

    /**
     * @param string[] $groups
     */
    public function withMiddlewareGroups(array $groups): self
    {
        $clone                   = clone $this;
        $clone->middlewareGroups = $groups;

        return $clone;
    }

    public function routes(): RouteCollector
    {
        if ($this->routes === null) {
            throw new BadMethodCallException('Router is not attached to the application.');
        }

        return $this->routes;
    }

    public function routesCached(): bool
    {
        return $this->routesCached;
    }

    public function middlewareManager(): MiddlewareManager
    {
        return $this->middlewareManager;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $stack = $this->resolveMiddlewareStack();

        if ($stack === []) {
            return $this->handler->handle($request);
        }

        $handler = $this->handler;

        foreach (array_reverse($stack) as $middleware) {
            $handler = new class ($middleware, $handler) implements RequestHandlerInterface {
                public function __construct(
                    private MiddlewareInterface $middleware,
                    private RequestHandlerInterface $handler,
                ) {
                }

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->middleware->process($request, $this->handler);
                }
            };
        }

        return $handler->handle($request);
    }

    public function run(?ServerRequestInterface $request = null, ?EmitterInterface $emitter = null): ResponseInterface
    {
        $request ??= ($this->requestCreator?->fromGlobals() ?? new ServerRequestCreator()->fromGlobals());

        $response = $this->handle($request);

        $emitter ??= $this->emitter ?? new SapiEmitter();
        $emitter->emit($response);

        return $response;
    }

    /**
     * @return list<MiddlewareInterface>
     */
    private function resolveMiddlewareStack(): array
    {
        $stack = $this->middlewareManager->stack($this->middlewareGroups);

        if ($stack === []) {
            return [];
        }

        $resolved = [];

        foreach ($stack as $middleware) {
            $resolved[] = $this->resolveMiddleware($middleware);
        }

        return $resolved;
    }

    /**
     */
    private function resolveMiddleware(MiddlewareInterface|string $middleware): MiddlewareInterface
    {
        $middleware = $this->middlewareManager->resolveAlias($middleware);

        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        if (is_string($middleware)) {
            if ($this->container !== null) {
                $instance = $this->container->get($middleware);
                if (!$instance instanceof MiddlewareInterface) {
                    throw new InvalidArgumentException("Resolved middleware must implement MiddlewareInterface: {$middleware}");
                }

                return $instance;
            }

            $instance = new $middleware();

            if (!$instance instanceof MiddlewareInterface) {
                throw new InvalidArgumentException("Resolved middleware must implement MiddlewareInterface: {$middleware}");
            }

            return $instance;
        }

        $type = get_debug_type($middleware);

        throw new InvalidArgumentException("Unsupported middleware definition: {$type}");
    }

    private function assertRoutesNotCached(): void
    {
        if ($this->routesCached) {
            throw new RuntimeException('Routes are cached. Clear route cache before registering new routes.');
        }
    }

    /**
     * @return string[]
     */
    private function normalizeRoutesPaths(array|string|null $paths): array
    {
        if ($paths === null) {
            return [];
        }

        $paths = is_array($paths) ? $paths : [$paths];

        $normalized = [];
        foreach ($paths as $path) {
            if (!is_string($path) || $path === '') {
                continue;
            }

            $normalized[] = rtrim($path, '/');
        }

        return $normalized;
    }

    /**
     * @return string[]
     */
    private function collectRouteFiles(string $path): array
    {
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            if ($file->getExtension() !== 'php') {
                continue;
            }

            $files[] = $file->getPathname();
        }

        sort($files, SORT_STRING);

        return $files;
    }

    private function applyMiddlewareConfig(array $config): void
    {
        foreach ($config['aliases'] ?? [] as $alias => $middleware) {
            $this->alias((string) $alias, $middleware);
        }

        foreach ($config['groups'] ?? [] as $group => $middlewares) {
            $this->middlewareGroup((string) $group, (array) $middlewares);
        }

        foreach ($config['global'] ?? [] as $middleware) {
            $this->add($middleware);
        }
    }

    public function controllerMiddleware(string $controller, array $middlewares, array $only = [], array $except = []): void
    {
        $this->assertRoutesNotCached();
        $this->routes()->addControllerMiddleware($controller, $middlewares, $only, $except);
    }

    /**
     * @param list<MiddlewareInterface|string> $middlewares
     * @param string|list<string>|null $host
     */
    public function group(
        string $prefix,
        callable $callback,
        array $middlewares = [],
        string|array|null $host = null,
        ?string $namePrefix = null,
    ): void {
        $this->assertRoutesNotCached();
        $this->routes()
            ->group($callback)
            ->prefix($prefix)
            ->middlewares($middlewares)
            ->host($host)
            ->namePrefix($namePrefix)
            ->apply();
    }

    public function resource(
        string $path,
        string $controller,
    ): ResourceBuilder {
        $this->assertRoutesNotCached();

        return $this->routes()->resource($path, $controller);
    }

    public function get(
        string $path,
        callable|array|string $handler,
    ): RouteBuilder {
        $this->assertRoutesNotCached();

        return $this->routes()->get($path, $handler);
    }

    public function post(
        string $path,
        callable|array|string $handler,
    ): RouteBuilder {
        $this->assertRoutesNotCached();

        return $this->routes()->post($path, $handler);
    }

    public function put(
        string $path,
        callable|array|string $handler,
    ): RouteBuilder {
        $this->assertRoutesNotCached();

        return $this->routes()->put($path, $handler);
    }

    public function delete(
        string $path,
        callable|array|string $handler,
    ): RouteBuilder {
        $this->assertRoutesNotCached();

        return $this->routes()->delete($path, $handler);
    }

    public function any(
        string $path,
        callable|array|string $handler,
    ): RouteBuilder {
        $this->assertRoutesNotCached();

        return $this->routes()->any($path, $handler);
    }

    public function patch(
        string $path,
        callable|array|string $handler,
    ): RouteBuilder {
        $this->assertRoutesNotCached();

        return $this->routes()->patch($path, $handler);
    }

    public function head(
        string $path,
        callable|array|string $handler,
    ): RouteBuilder {
        $this->assertRoutesNotCached();

        return $this->routes()->head($path, $handler);
    }

    public function options(
        string $path,
        callable|array|string $handler,
    ): RouteBuilder {
        $this->assertRoutesNotCached();

        return $this->routes()->options($path, $handler);
    }

    public function map(
        string $method,
        string $path,
        callable|array|string $handler,
    ): RouteBuilder {
        $this->assertRoutesNotCached();

        return $this->routes()->map($method, $path, $handler);
    }
}
