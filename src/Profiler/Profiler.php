<?php

namespace Georgeff\Kernel\Profiler;

use Georgeff\Kernel\Exception\ProfilerException;
use Georgeff\Kernel\Contract\DebuggableInterface;

/**
 * @internal
 */
final class Profiler implements DebuggableInterface
{
    /**
     * @var array<string, Profile>
     */
    private array $profiles = [];

    /**
     * @var array<string, DebuggableInterface>
     */
    private array $registry = [];

    public function initProfile(string $name): Profile
    {
        $profile = new Profile();

        $profile->start();

        return $this->profiles[$name] = $profile;
    }

    public function hasProfile(string $name): bool
    {
        return isset($this->profiles[$name]);
    }

    public function getProfile(string $name): Profile
    {
        ProfilerException::throwIfNot($this->hasProfile($name), "Profile [$name] has not been initialized");

        return $this->profiles[$name];
    }

    public function removeProfile(string $name): void
    {
        unset($this->profiles[$name]);
    }

    /**
     * Register debuggable services to merge into debug info
     */
    public function register(DebuggableInterface $service, ?string $name = null): void
    {
        $name ??= $service::class;

        $this->registry[$name] = $service;
    }

    public function getDebugInfo(): array
    {
        $output = [];

        foreach ($this->profiles as $name => $profile) {
            $output['profiles'][$name] = $profile->getDebugInfo();
        }

        foreach ($this->registry as $name => $debuggle) {
            $output['components'][$name] = $debuggle->getDebugInfo();
        }

        return $output;
    }
}
