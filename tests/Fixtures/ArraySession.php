<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Tests\Fixtures;

use PhpSoftBox\Session\SessionInterface;

use function array_key_exists;

/**
 * Сессия в памяти для тестов редиректов: хранит данные и flash без cookie и хранилища.
 */
final class ArraySession implements SessionInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * @var array<string, mixed>
     */
    private array $flash = [];

    private bool $started = false;

    public function start(): void
    {
        $this->started = true;
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    public function all(): array
    {
        return $this->data;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function forget(string $key): void
    {
        unset($this->data[$key]);
    }

    public function clear(): void
    {
        $this->data  = [];
        $this->flash = [];
    }

    public function flash(string $key, mixed $value): void
    {
        $this->flash[$key] = $value;
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        return $this->flash[$key] ?? $default;
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->data[$key] ?? $default;
        unset($this->data[$key]);

        return $value;
    }

    public function save(): void
    {
    }

    public function regenerate(bool $deleteOldSession = true): void
    {
    }

    public function destroy(): void
    {
        $this->clear();
    }
}
