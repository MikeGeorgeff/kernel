<?php

namespace Georgeff\Kernel\Support;

use Georgeff\Kernel\Contract\EnvironmentInterface;

final class EnvironmentResolver
{
    /**
     * @var array<string, class-string<EnvironmentInterface>>
     */
    private array $registry = [
        'production'  => \Georgeff\Kernel\Environment\Production::class,
        'staging'     => \Georgeff\Kernel\Environment\Staging::class,
        'development' => \Georgeff\Kernel\Environment\Development::class,
        'testing'     => \Georgeff\Kernel\Environment\Testing::class,
        'local'       => \Georgeff\Kernel\Environment\Local::class,
    ];

    /**
     * @param class-string<EnvironmentInterface> $class
     */
    public function register(string $name, string $class): self
    {
        if (!class_exists($class)) {
            throw new \LogicException("Environment class [$class] was not found");
        }

        if (!is_subclass_of($class, EnvironmentInterface::class)) {
            throw new \LogicException("Environment class [$class] must be an instance of " . EnvironmentInterface::class);
        }

        $this->registry[$name] = $class;

        return $this;
    }

    public function resolve(string $name): EnvironmentInterface
    {
        if (!isset($this->registry[$name])) {
            throw new \InvalidArgumentException("Environment [$name] is not a registered environment");
        }

        $env = $this->registry[$name];

        return new $env();
    }

    /**
     * @return array<string, class-string<EnvironmentInterface>>
     */
    public function registered(): array
    {
        return $this->registry;
    }
}
