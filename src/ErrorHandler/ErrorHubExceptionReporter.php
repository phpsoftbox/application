<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\ErrorHandler;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use PhpSoftBox\Collection\Collection;
use PhpSoftBox\ErrorFormatter\ThrowableFormatter;
use PhpSoftBox\Http\Client\HttpClient;
use PhpSoftBox\Http\Message\RequestFactory;
use PhpSoftBox\Http\Message\ResponseFactory;
use PhpSoftBox\Http\Message\StreamFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;
use Throwable;

use function array_unique;
use function array_values;
use function base64_encode;
use function explode;
use function is_array;
use function is_int;
use function is_scalar;
use function is_string;
use function json_encode;
use function preg_replace;
use function rtrim;
use function sprintf;
use function str_replace;
use function trim;

use const CURLOPT_SSL_VERIFYHOST;
use const CURLOPT_SSL_VERIFYPEER;
use const CURLOPT_TIMEOUT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final readonly class ErrorHubExceptionReporter implements ExceptionReporterInterface
{
    private DateTimeZone $timezone;

    /**
     * @param list<string> $tags
     * @param array<string, mixed> $context
     */
    public function __construct(
        private string $baseUrl,
        private string $projectKey,
        private array $tags = [],
        private array $context = [],
        private int $timeout = 2,
        private bool $verifyPeer = true,
        private ?string $token = null,
        private ?string $username = null,
        private ?string $password = null,
        private ?ClientInterface $client = null,
        private ?RequestFactoryInterface $requestFactory = null,
        private ?StreamFactoryInterface $streamFactory = null,
        private ?Closure $contextResolver = null,
        private ?Closure $userResolver = null,
        string $timezone = 'UTC',
    ) {
        try {
            $this->timezone = new DateTimeZone($timezone);
        } catch (Throwable) {
            $this->timezone = new DateTimeZone('UTC');
        }
    }

    public function report(Throwable $exception, ?ExceptionReportContext $reportContext = null): void
    {
        if ($this->baseUrl === '' || $this->projectKey === '') {
            return;
        }

        $payload = $this->buildPayload($exception, $reportContext);

        try {
            $this->send($payload);
        } catch (Throwable) {
            // Do not break the app on reporting errors.
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(Throwable $exception, ?ExceptionReportContext $reportContext = null): array
    {
        $context = new Collection();
        $request = $reportContext?->request;
        if ($request !== null) {
            $context->add('method', $request->getMethod());
            $context->add('uri', (string) $request->getUri());

            $requestId = trim($request->getHeaderLine('X-Request-Id'));
            if ($requestId !== '') {
                $context->add('request_id', $requestId);
            }
        }

        if ($this->context !== []) {
            $context = $context->merge($this->context);
        }

        if ($reportContext?->data !== []) {
            $context = $context->merge($reportContext->data);
        }

        if ($this->contextResolver !== null && $request !== null) {
            $resolved = ($this->contextResolver)($exception, $request);
            if (is_array($resolved)) {
                $context = $context->merge($resolved);
            }
        }

        if ($exception instanceof ErrorHubExceptionInterface) {
            $context = $context
                ->merge($exception->getErrorHubContext(), ['recursive' => true, 'list' => 'append_unique']);
        }

        $user = null;
        if ($this->userResolver !== null && $request !== null) {
            $resolvedUser = ($this->userResolver)($exception, $request);
            if (is_array($resolvedUser)) {
                $user = $resolvedUser;
            }
        }

        $tags = new Collection($this->normalizeTags($this->tags));

        if ($reportContext?->tags !== []) {
            $tags = $tags
                ->merge($this->normalizeTags($reportContext->tags), ['recursive' => true, 'list' => 'append_unique'])
                ->filter(static fn (mixed $tag): bool => is_string($tag) && $tag !== '')
                ->unique()
                ->values();
        }

        if ($exception instanceof ErrorHubExceptionInterface) {
            $tags = $tags
                ->merge($this->normalizeTags($exception->getErrorHubTags()), ['recursive' => true, 'list' => 'append_unique'])
                ->filter(static fn (mixed $tag): bool => is_string($tag) && $tag !== '')
                ->unique()
                ->values();
        }

        $payload = [
            'timestamp' => $this->utcTimestamp(),
            'level'     => 'error',
            'message'   => $exception->getMessage(),
            'exception' => [
                'class'      => $exception::class,
                'message'    => $exception->getMessage(),
                'stacktrace' => $this->formatTrace($exception),
            ],
            'tags'    => $tags->all(),
            'context' => $context->all(),
        ];

        if ($user !== null) {
            $payload['user'] = $user;
        }

        return $this->sanitizePayload($payload);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function formatTrace(Throwable $exception): array
    {
        return ThrowableFormatter::toFrames($exception);
    }

    private function utcTimestamp(): string
    {
        return new DateTimeImmutable('now', $this->timezone)->format('Y-m-d\\TH:i:s\\Z');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function send(array $payload): void
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            throw new RuntimeException('Unable to encode ErrorHub payload.');
        }

        $headers = [
            'Content-Type: application/json',
            'X-Project-Key: ' . $this->projectKey,
        ];

        if ($this->token !== null && $this->token !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        } elseif ($this->username !== null) {
            $pass      = $this->password ?? '';
            $headers[] = 'Authorization: Basic ' . base64_encode($this->username . ':' . $pass);
        }

        $client         = $this->client ?? $this->createClient();
        $requestFactory = $this->requestFactory ?? new RequestFactory();
        $streamFactory  = $this->streamFactory ?? new StreamFactory();

        $request = $requestFactory->createRequest('POST', $this->resolveEndpoint());
        foreach ($headers as $header) {
            if (!is_string($header) || $header === '') {
                continue;
            }
            [$name, $value] = explode(':', $header, 2);
            $request        = $request->withHeader(trim($name), trim($value));
        }
        $request = $request->withBody($streamFactory->createStream($body));

        $response   = $client->sendRequest($request);
        $statusCode = $response->getStatusCode();
        if ($statusCode >= 400) {
            throw new RuntimeException(sprintf('ErrorHub responded with status %d', $statusCode));
        }
    }

    private function resolveEndpoint(): string
    {
        $baseUrl = trim($this->baseUrl);
        if ($baseUrl === '') {
            return '';
        }

        $baseUrl = rtrim($baseUrl, '/');

        return $baseUrl . '/api/v1/ingest';
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function sanitizePayload(array $payload): array
    {
        foreach ($payload as $key => $value) {
            $payload[$key] = $this->sanitizeValue($value);
        }

        return $payload;
    }

    private function sanitizeValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->stripControlChars($value);
        }

        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $k => $v) {
                $key             = is_string($k) ? $this->stripControlChars($k) : $k;
                $sanitized[$key] = $this->sanitizeValue($v);
            }
            $value = $sanitized;
        }

        return $value;
    }

    private function stripControlChars(string $value): string
    {
        $sanitized = preg_replace('/[\\x00-\\x1F\\x7F]/', '', $value);
        if ($sanitized === null) {
            $sanitized = str_replace("\0", '', $value);
        }

        return $sanitized;
    }

    /**
     * @param array<int|string, mixed> $tags
     * @return list<string>
     */
    private function normalizeTags(array $tags): array
    {
        $normalized = [];

        foreach ($tags as $key => $value) {
            if (is_int($key)) {
                if (is_string($value) && $value !== '') {
                    $normalized[] = $value;
                } elseif (is_scalar($value)) {
                    $normalized[] = (string) $value;
                }
                continue;
            }

            if ($value === null || $value === '') {
                $normalized[] = (string) $key;
                continue;
            }

            if (is_scalar($value)) {
                $normalized[] = (string) $key . ':' . (string) $value;
                continue;
            }

            $normalized[] = (string) $key;
        }

        return array_values(array_unique($normalized));
    }

    private function createClient(): ClientInterface
    {
        $options = [
            CURLOPT_SSL_VERIFYPEER => $this->verifyPeer,
            CURLOPT_SSL_VERIFYHOST => $this->verifyPeer ? 2 : 0,
        ];

        if ($this->timeout > 0) {
            $options[CURLOPT_TIMEOUT] = $this->timeout;
        }

        return new HttpClient(
            new ResponseFactory(),
            new StreamFactory(),
            $options,
            new RequestFactory(),
        );
    }
}
