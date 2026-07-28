<?php

namespace Georgeff\Kernel\Support;

use Georgeff\Kernel\Contract\EnvironmentInterface;
use Georgeff\Kernel\Exception\EnvironmentException;

/**
 * Optional name-string -> EnvironmentInterface class registry, e.g. for turning APP_ENV
 * into an environment instance at bootstrap. Not wired into Kernel; construct an
 * EnvironmentInterface however you like if you don't want this. Ships pre-registered with
 * the five stock environments (production, staging, development, testing, local).
 */
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
     *
     * @throws EnvironmentException
     */
    public function register(string $name, string $class): self
    {
        if (!class_exists($class)) {
            throw new EnvironmentException("Environment class [$class] was not found");
        }

        if (!is_subclass_of($class, EnvironmentInterface::class)) {
            throw new EnvironmentException("Environment class [$class] must be an instance of " . EnvironmentInterface::class);
        }

        $this->registry[$name] = $class;

        return $this;
    }

    /**
     * @throws EnvironmentException
     */
    public function resolve(string $name): EnvironmentInterface
    {
        if (!isset($this->registry[$name])) {
            throw new EnvironmentException("Environment [$name] is not a registered environment");
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
