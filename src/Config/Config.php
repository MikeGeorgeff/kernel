<?php

namespace Georgeff\Kernel\Config;

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
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->config);
    }

    public function get(string $name, mixed $default = null): mixed
    {
        return $this->has($name) ? $this->config[$name] : $default;
    }
}
