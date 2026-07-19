<?php

namespace Georgeff\Kernel\Storage;

use Georgeff\Kernel\Debug\DebuggableInterface;
use Georgeff\Kernel\Contract\ResettableInterface;

/**
 * @internal
 */
final class Cache implements DebuggableInterface, ResettableInterface
{
    /**
     * @var array<string, string|int|float|bool|array<array-key, mixed>|null>
     */
    private array $cache = [];

    /**
     * @return array<string, string|int|float|bool|array<array-key, mixed>|null>
     */
    public function all(): array
    {
        return $this->cache;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->cache);
    }

    /**
     * @param string|int|float|bool|array<array-key, mixed>|null $value
     */
    public function set(string $key, string|int|float|bool|array|null $value): void
    {
        $this->cache[$key] = $value;
    }

    /**
     * @return string|int|float|bool|array<array-key, mixed>|null
     */
    public function get(string $key): string|int|float|bool|array|null
    {
        if (!$this->has($key)) {
            throw new \InvalidArgumentException("Cache key [$key] was not found");
        }

        return $this->cache[$key];
    }

    public function getString(string $key): string
    {
        if (!is_string($value = $this->get($key))) {
            throw $this->newTypeError($key, $value, 'string');
        }

        return $value;
    }

    public function getInteger(string $key): int
    {
        if (!is_int($value = $this->get($key))) {
            throw $this->newTypeError($key, $value, 'integer');
        }

        return $value;
    }

    public function getFloat(string $key): float
    {
        if (!is_float($value = $this->get($key))) {
            throw $this->newTypeError($key, $value, 'float');
        }

        return $value;
    }

    public function getBoolean(string $key): bool
    {
        if (!is_bool($value = $this->get($key))) {
            throw $this->newTypeError($key, $value, 'boolean');
        }

        return $value;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getArray(string $key): array
    {
        if (!is_array($value = $this->get($key))) {
            throw $this->newTypeError($key, $value, 'array');
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $merge
     */
    public function mergeArray(string $key, array $merge): void
    {
        $value = $this->has($key) ? $this->getArray($key) : [];

        $this->set($key, $merge + $value);
    }

    public function remove(string $key): void
    {
        unset($this->cache[$key]);
    }

    public function getDebugInfo(): array
    {
        return $this->all();
    }

    public function reset(): void
    {
        $this->cache = [];
    }

    /**
     * @return \TypeError
     */
    private function newTypeError(string $key, mixed $value, string $expected): \TypeError
    {
        return new \TypeError(sprintf(
            'Invalid type for cache key [%s]. Expected [%s] got [%s]',
            $key,
            $expected,
            get_debug_type($value)
        ));
    }
}
