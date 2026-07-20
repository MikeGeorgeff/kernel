<?php

namespace Georgeff\Kernel\Config;

use Georgeff\Kernel\Exception\ConfigException;

/**
 * @internal
 */
final class Config implements ConfigInterface
{
    /**
     * @var array<string, mixed>
     */
    private readonly array $config;

    /**
     * @var array<string, ConfigInterface>
     */
    private array $branchCache = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function isEmpty(): bool
    {
        return empty($this->config);
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->config);
    }

    public function get(string $name, mixed $default = null): mixed
    {
        return $this->has($name) ? $this->config[$name] : $default;
    }

    public function branch(string $name): ConfigInterface
    {
        if (isset($this->branchCache[$name])) {
            return $this->branchCache[$name];
        }

        $value = $this->has($name) ? $this->config[$name] : [];

        self::assertBranch($value, $name);

        return $this->branchCache[$name] = new self($value);
    }

    /**
     * @phpstan-assert array<string, mixed> $value
     */
    private static function assertBranch(mixed $value, string $name): void
    {
        if (!is_array($value)) {
            throw new ConfigException(
                "Branch expects value of [$name] to be an array, given: " . get_debug_type($value)
            );
        }

        foreach ($value as $key => $_) {
            if (!is_string($key) || is_numeric($key)) {
                throw new ConfigException(
                    "Branch expects array for [$name] to have non-numeric keys"
                );
            }
        }
    }
}
