# Application

Минимальное приложение для работы с PSR-15 пайплайном.

## Окружение приложения

Приложение определяет собственный string-backed enum и реализует framework-контракт:

```php
use PhpSoftBox\Application\ApplicationEnvironment;
use PhpSoftBox\Application\Contracts\EnvironmentEnumInterface;

enum Environment: string implements EnvironmentEnumInterface
{
    case DEV = 'dev';
    case DEMO = 'demo';
    case PROD = 'prod';
}

$environment = new ApplicationEnvironment(Environment::DEV);

$environment->current();                 // Environment::DEV
$environment->value();                   // 'dev'
$environment->is(Environment::DEV);      // true
```

`ApplicationEnvironment` также принимает `EnvironmentPolicyInterface` и нормализованный запрос
debug. Без policy методы `isDebug()` и `isProductionLike()` возвращают `false`.

## Быстрый старт

```php
use PhpSoftBox\Application\Application;
use PhpSoftBox\Application\ErrorHandler\JsonExceptionHandler;
use PhpSoftBox\Application\ErrorHandler\HtmlExceptionHandler;
use PhpSoftBox\Application\ErrorHandler\ContentNegotiationExceptionHandler;
use PhpSoftBox\Application\Middleware\ErrorHandlerMiddleware;
use PhpSoftBox\Router\Router;

$router = new Router($resolver, $dispatcher, $collector);
$exceptionHandler = new ContentNegotiationExceptionHandler(
    new JsonExceptionHandler($responseFactory, $streamFactory, includeDetails: true),
    new HtmlExceptionHandler($responseFactory, $streamFactory, includeDetails: true),
);

$app = new Application($router, [
    new ErrorHandlerMiddleware($exceptionHandler),
]);

$response = $app->handle($request);
```

## Настройка формата ошибок (Deciders)

`ContentNegotiationExceptionHandler` поддерживает реестр deciders для выбора формата ответа на ошибку.
Decider возвращает `ExceptionFormat::JSON`, `ExceptionFormat::HTML` или `null` (тогда срабатывает стандартная логика по `Accept` и `X-Requested-With`).

```php
use PhpSoftBox\Application\ErrorHandler\ContentNegotiationExceptionHandler;
use PhpSoftBox\Application\ErrorHandler\ExceptionFormat;
use PhpSoftBox\Application\ErrorHandler\ExceptionFormatDeciderInterface;
use PhpSoftBox\Application\ErrorHandler\ExceptionFormatDeciderRegistry;
use PhpSoftBox\Application\ErrorHandler\HtmlExceptionHandler;
use PhpSoftBox\Application\ErrorHandler\JsonExceptionHandler;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class ApiExceptionDecider implements ExceptionFormatDeciderInterface
{
    public function __invoke(Throwable $exception, ServerRequestInterface $request): ?ExceptionFormat
    {
        $path = ltrim($request->getUri()->getPath(), '/');
        if (str_starts_with($path, 'api/')) {
            return ExceptionFormat::JSON;
        }

        if ($request->getHeaderLine('X-Inertia') !== '') {
            return ExceptionFormat::HTML;
        }

        return null;
    }
}

$deciders = new ExceptionFormatDeciderRegistry([
    new ApiExceptionDecider(),
]);

$exceptionHandler = new ContentNegotiationExceptionHandler(
    new JsonExceptionHandler($responseFactory, $streamFactory, includeDetails: true),
    new HtmlExceptionHandler($responseFactory, $streamFactory, includeDetails: true),
    $deciders,
);
```

## Репортинг ошибок в ErrorHub

```php
use PhpSoftBox\Application\ErrorHandler\DefaultExceptionHandler;
use PhpSoftBox\Application\ErrorHandler\ErrorHubExceptionReporter;
use PhpSoftBox\Application\ErrorHandler\LoggerExceptionReporter;
use PhpSoftBox\Application\Middleware\ErrorHandlerMiddleware;

$errorhubReporter = new ErrorHubExceptionReporter(
    baseUrl: 'https://errorhub.getstash-dev.ru',
    projectKey: 'YOUR_PROJECT_KEY',
    token: 'YOUR_BEARER_TOKEN', // опционально
    username: null, // опционально (basic auth)
    password: null,
    tags: ['env:prod', 'app:backend'],
);

$fallback = new ContentNegotiationExceptionHandler(
    new JsonExceptionHandler($responseFactory, $streamFactory, includeDetails: true),
    new HtmlExceptionHandler($responseFactory, $streamFactory, includeDetails: true),
);

$exceptionHandler = new DefaultExceptionHandler(
    fallbackHandler: $fallback,
    responseFactory: $responseFactory,
    session: $session,
    reporters: [
        new LoggerExceptionReporter($logger),
        $errorhubReporter,
    ],
);

$app = new Application($router, [
    new ErrorHandlerMiddleware($exceptionHandler),
]);
```

Если нужно добавить теги на уровне конкретного исключения:

```php
use PhpSoftBox\Application\ErrorHandler\ErrorHubExceptionInterface;
use PhpSoftBox\Application\ErrorHandler\ErrorHubExceptionTrait;

final class BillingException extends \RuntimeException implements ErrorHubExceptionInterface
{
    use ErrorHubExceptionTrait;
}

$e = new BillingException('Payment failed');
$e->setErrorHubTags(['module:billing']);
$e->setErrorHubContext(['order_id' => 42]);
```

### Обработанные (soft) исключения

`SoftExceptionReporter` используется, когда вызывающий код осознанно выбирает fallback,
но исключение нельзя скрывать из logger/ErrorHub:

```php
use PhpSoftBox\Application\ErrorHandler\CompositeExceptionReporter;
use PhpSoftBox\Application\ErrorHandler\ExceptionReportContext;
use PhpSoftBox\Application\ErrorHandler\SoftExceptionMode;
use PhpSoftBox\Application\ErrorHandler\SoftExceptionReporter;

$exceptionReporter = new CompositeExceptionReporter([
    new LoggerExceptionReporter($logger),
    $errorhubReporter,
]);

$softExceptions = new SoftExceptionReporter(
    reporter: $exceptionReporter,
    mode: SoftExceptionMode::Report,
);

try {
    $params['sign'] = SignatureFactory::getSignature($url);
} catch (SignatureException $exception) {
    $softExceptions->report(
        $exception,
        new ExceptionReportContext(
            data: ['integration' => 'marketplace'],
            tags: ['signature'],
        ),
    );

    return null;
}
```

В тестовом bootstrap можно зарегистрировать `SoftExceptionMode::Throw`. Тогда `report()`
бросает тот же экземпляр исключения и тест не пропускает fallback-ветку незаметно.
В режиме `Report` ошибка любого конкретного reporter-а не меняет основной control flow.

`ExceptionReporterInterface` не требует HTTP request. Для необработанных HTTP-исключений
`DefaultExceptionHandler` добавляет request через `ExceptionReportContext::fromRequest()`;
CLI, queue и интеграции передают только доступный им контекст.

## RouterFactory + RouteCache

```php
use PhpSoftBox\Application\RouterFactory;
use PhpSoftBox\Router\Cache\RouteCache;
use PhpSoftBox\Router\Dispatcher;

$cache = new RouteCache($cacheStorage);
$factory = new RouterFactory(new Dispatcher(), $cache);

$router = $factory->create(function ($routes) {
    $routes->get('/users', [UserController::class, 'index']);
});
```

## AppFactory

```php
use PhpSoftBox\Application\AppFactory;

$app = AppFactory::createFromContainer($container, environment: 'prod');

if (!$app->routesCached()) {
    require __DIR__ . '/routes.php';
}
```

## Регистрация middleware

```php
use PhpSoftBox\Application\Application;
use PhpSoftBox\Application\Middleware\ErrorHandlerMiddleware;
use PhpSoftBox\Application\Middleware\RequestSizeLimitMiddleware;
use PhpSoftBox\Session\SessionMiddleware;

$app = new Application($router, container: $container);

$app->add(new ErrorHandlerMiddleware($exceptionHandler), priority: 100);
$app->add(RequestSizeLimitMiddleware::class);

$app->alias('session', SessionMiddleware::class);
$app->middlewareGroup('web', ['session']);
```

## Группы middleware

```php
$webApp = $app->withMiddlewareGroups(['web']);
$response = $webApp->handle($request);
```

## Регистрация роутов через Application

```php
$app->get('/users', [UserController::class, 'index']);
$app->post('/users', [UserController::class, 'store']);
```

Методы проксируются в `RouteCollector`, если приложение создано с `Router`.

## Middleware для контроллеров

```php
$app->controllerMiddleware(UserController::class, ['auth']);
$app->controllerMiddleware(UserController::class, ['admin'], only: ['store', 'update']);
```

Рекомендуемый путь привязки Middleware — регистрация через Router на маршруты/группы; контроллеры/экшены используйте точечно.

## Группы middleware для маршрутов

```php
use PhpSoftBox\Application\Middleware\KernelRouteMiddlewareResolver;
use PhpSoftBox\Router\Dispatcher;
use PhpSoftBox\Router\Router;

$app->alias('auth', \PhpSoftBox\Auth\Middleware\AuthMiddleware::class);
$app->middlewareGroup('api', ['auth']);

$dispatcher = new Dispatcher(
    handlerResolver: null,
    middlewareResolver: new KernelRouteMiddlewareResolver($app->middlewareManager(), $container),
);

$router = new Router($resolver, $dispatcher, $collector);

$collector->group('/api', function ($routes) {
    $routes->get('/users', [UserController::class, 'index']);
}, ['api']);
```

## Ответы

В приложении доступны готовые ответы:
- `JsonResponse`
- `HtmlResponse`
- `XmlResponse`
- `TextResponse`

Пример:

```php
use PhpSoftBox\Application\Response\JsonResponse;

return new JsonResponse(['ok' => true]);
```

## Ошибки роутера

`InvalidRouteParameterException` (например, когда параметр не проходит валидацию) в прод-режиме
возвращает 404 `Not Found`. В debug-режиме сообщение исключения возвращается в ответе.

## Машиночитаемые JSON-ошибки

`JsonExceptionHandler` всегда добавляет стабильное поле `code`. Для предметных
публичных ошибок используйте `CodedHttpException` либо преобразуйте исходное
исключение через `ExceptionMapperRegistry`:

```php
throw new CodedHttpException(
    statusCode: 406,
    errorCode: 'unsupported_api_version',
    displayMessage: 'Requested API version is not supported.',
    details: ['supported_versions' => ['1.0.0', '1.1.0']],
    title: 'Unsupported API version',
    debugMessage: $internalDiagnostic,
);
```

`details` считаются публичными данными и попадают в production response.
`debugMessage`, исходное exception message и stack trace доступны только при
включённом debug-режиме. Это изменение формата: потребителям JSON-ошибок нужно
учесть новое обязательное поле `code`.

## Rate limit middleware

Middleware принимает проектный key resolver, нормализует результат через
`RateLimitKeyNormalizerInterface` и добавляет namespace. В production передавайте
атомарный limiter, например `RedisRateLimiter` из `phpsoftbox/rate-limiter`:

```php
$middleware = new RateLimitMiddleware(
    limiter: $redisRateLimiter,
    maxAttempts: 60,
    decaySeconds: 60,
    keyResolver: static fn (ServerRequestInterface $request): string =>
        (string) $request->getAttribute(AuthenticatedNode::class)->id(),
    namespace: 'node_api',
);
```

`X-RateLimit-Reset` теперь является абсолютным Unix timestamp, а `Retry-After` —
относительным числом секунд. Сырые credentials не следует возвращать из key
resolver.

## Авторизация приватных каналов Broadcaster

Обычно требуется эндпоинт `/broadcast/auth`, который выдаёт `auth` для приватных каналов.
Пример с `PushrChannelAuth`:

```php
use PhpSoftBox\Broadcaster\Pushr\PushrChannelAuth;
use PhpSoftBox\Http\Message\Response;
use Psr\Http\Message\ServerRequestInterface;
use function json_encode;

$app->post('/broadcast/auth', function (ServerRequestInterface $request, \PhpSoftBox\Broadcaster\Channel\ChannelRegistry $channels): Response {
    $data = (array) ($request->getParsedBody() ?? []);

    $socketId = (string) ($data['socket_id'] ?? '');
    $channel = (string) ($data['channel'] ?? '');
    $channelData = $data['channel_data'] ?? null;

    $authorization = $channels->authorize($channel, $request);
    if (!$authorization->authorized()) {
        return new Response(403);
    }

    $channelData = $authorization->channelData() ?? $channelData;

    $auth = PushrChannelAuth::token('app-1', 'secret-1', $socketId, $channel, $channelData);

    return new Response(200, ['Content-Type' => 'application/json'], json_encode([
        'auth' => $auth,
        'channel_data' => $channelData,
    ]));
});
```

`socket_id` клиент получает из события `connection` после подключения к WebSocket.
