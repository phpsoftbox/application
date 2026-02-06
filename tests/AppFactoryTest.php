<?php

declare(strict_types=1);

namespace PhpSoftBox\Tests\Application;

use PhpSoftBox\Application\AppFactory;
use PhpSoftBox\Application\Application;
use PhpSoftBox\Router\Cache\RouteCache;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

use function bin2hex;
use function file_put_contents;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

final class AppFactoryTest extends TestCase
{
    public function testIgnoresMissingRouteCacheDependencies(): void
    {
        $container = new class () implements ContainerInterface {
            public function get(string $id): mixed
            {
                if ($id === RouteCache::class) {
                    throw new RuntimeException('RouteCache is not resolvable');
                }

                throw new RuntimeException('Not found');
            }

            public function has(string $id): bool
            {
                return $id === RouteCache::class;
            }
        };

        $app = AppFactory::createFromContainer($container, environment: 'dev');

        $this->assertInstanceOf(Application::class, $app);
    }

    public function testRegistersRoutesFromPath(): void
    {
        $routesDir = sys_get_temp_dir() . '/psb_routes_' . bin2hex(random_bytes(4));
        mkdir($routesDir, 0777, true);

        $routeFile = $routesDir . '/web.php';
        file_put_contents(
            $routeFile,
            "<?php\n\nuse PhpSoftBox\\Router\\RouteCollector;\n\nreturn static function (RouteCollector \$routes): void {\n    \$routes->get('/health', static fn () => 'OK');\n};\n",
        );

        $container = new class () implements ContainerInterface {
            public function get(string $id): mixed
            {
                throw new RuntimeException('Not found');
            }

            public function has(string $id): bool
            {
                return false;
            }
        };

        try {
            $app = AppFactory::createFromContainer($container, environment: 'dev', routesPath: $routesDir);
            $this->assertCount(1, $app->routes()->getRoutes());
        } finally {
            unlink($routeFile);
            rmdir($routesDir);
        }
    }

    public function testRegisterErrorHandlerMiddlewareThrowsWhenMissingHandler(): void
    {
        $container = new class () implements ContainerInterface {
            public function get(string $id): mixed
            {
                throw new RuntimeException('Not found');
            }

            public function has(string $id): bool
            {
                return false;
            }
        };

        $app = AppFactory::createFromContainer($container, environment: 'dev');

        $this->expectException(RuntimeException::class);
        $app->registerErrorHandlerMiddleware();
    }
}
