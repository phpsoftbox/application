<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Perf;

use RuntimeException;

use function date;
use function file_put_contents;
use function http_response_code;
use function implode;
use function is_array;
use function is_dir;
use function memory_get_peak_usage;
use function memory_get_usage;
use function microtime;
use function mkdir;
use function register_shutdown_function;
use function rtrim;
use function sprintf;

use const FILE_APPEND;

final class PerfLog
{
    private function __construct()
    {
    }

    public static function registerHttp(string $logDir, ?string $method = null, ?string $uri = null): void
    {
        $method ??= (string) ($_SERVER['REQUEST_METHOD'] ?? 'HTTP');
        $uri ??= (string) ($_SERVER['REQUEST_URI'] ?? '');

        self::register(
            logDir: $logDir,
            label: 'HTTP',
            context: ['method' => $method, 'uri' => $uri],
            extra: static function (): array {
                return ['status' => http_response_code() ?: 0];
            },
        );
    }

    public static function registerCli(string $logDir, ?array $argv = null): void
    {
        $argv ??= $_SERVER['argv'] ?? [];

        $command = is_array($argv) ? implode(' ', $argv) : '';

        self::register(
            logDir: $logDir,
            label: 'CLI',
            context: ['command' => $command],
            extra: null,
        );
    }

    /**
     * @param array<string, string|int|float> $context
     * @param null|callable(): array<string, string|int|float> $extra
     */
    private static function register(string $logDir, string $label, array $context, ?callable $extra): void
    {
        $startedAt   = microtime(true);
        $memoryStart = memory_get_usage();

        register_shutdown_function(static function () use ($logDir, $label, $context, $extra, $startedAt, $memoryStart): void {
            $durationMs = (microtime(true) - $startedAt) * 1000;
            $memoryPeak = memory_get_peak_usage();

            $data = $context;
            if ($extra !== null) {
                $data = $data + $extra();
            }

            $data['duration']  = sprintf('%.2fms', $durationMs);
            $data['mem_start'] = $memoryStart;
            $data['mem_peak']  = $memoryPeak;

            $parts = [];
            foreach ($data as $key => $value) {
                $parts[] = $key . '=' . $value;
            }

            $line = sprintf(
                "[%s] %s %s\n",
                date('c'),
                $label,
                implode(' ', $parts),
            );

            $logDir = rtrim($logDir, '/');
            if (!is_dir($logDir) && !mkdir($logDir, 0777, true) && !is_dir($logDir)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $logDir));
            }

            @file_put_contents($logDir . '/perf.log', $line, FILE_APPEND);
        });
    }
}
