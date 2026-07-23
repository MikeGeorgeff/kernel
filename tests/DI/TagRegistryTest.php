<?php

namespace Georgeff\Kernel\Test\DI;

use Georgeff\Kernel\DI\TagRegistry;
use Georgeff\Kernel\DI\TagRegistryInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class TagRegistryTest extends TestCase
{
    public function test_it_implements_tag_registry_interface(): void
    {
        $registry = new TagRegistry($this->makeContainer([]), []);

        $this->assertInstanceOf(TagRegistryInterface::class, $registry);
    }

    public function test_get_tagged_returns_empty_array_for_unknown_tag(): void
    {
        $registry = new TagRegistry($this->makeContainer([]), []);

        $this->assertSame([], $registry->getTagged('unknown'));
    }

    public function test_get_tagged_returns_empty_array_for_tag_with_no_services(): void
    {
        $registry = new TagRegistry($this->makeContainer([]), ['empty.tag' => []]);

        $this->assertSame([], $registry->getTagged('empty.tag'));
    }

    public function test_get_tagged_returns_resolved_service(): void
    {
        $service = new \stdClass();
        $registry = new TagRegistry(
            $this->makeContainer(['my.service' => $service]),
            ['my.tag' => ['my.service']],
        );

        $result = $registry->getTagged('my.tag');

        $this->assertCount(1, $result);
        $this->assertSame($service, $result[0]);
    }

    public function test_get_tagged_resolves_multiple_services(): void
    {
        $a = new \stdClass();
        $b = new \stdClass();
        $registry = new TagRegistry(
            $this->makeContainer(['service.a' => $a, 'service.b' => $b]),
            ['my.tag' => ['service.a', 'service.b']],
        );

        $result = $registry->getTagged('my.tag');

        $this->assertCount(2, $result);
        $this->assertSame($a, $result[0]);
        $this->assertSame($b, $result[1]);
    }

    public function test_get_tagged_preserves_registration_order(): void
    {
        $a = new \stdClass();
        $b = new \stdClass();
        $c = new \stdClass();
        $registry = new TagRegistry(
            $this->makeContainer(['s1' => $a, 's2' => $b, 's3' => $c]),
            ['my.tag' => ['s1', 's2', 's3']],
        );

        $this->assertSame([$a, $b, $c], $registry->getTagged('my.tag'));
    }

    public function test_get_tagged_resolves_services_through_container(): void
    {
        $service = new \stdClass();

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())
                  ->method('get')
                  ->with('my.service')
                  ->willReturn($service);

        $registry = new TagRegistry($container, ['my.tag' => ['my.service']]);

        $registry->getTagged('my.tag');
    }

    public function test_get_tagged_ids_returns_empty_array_for_unknown_tag(): void
    {
        $registry = new TagRegistry($this->makeContainer([]), []);

        $this->assertSame([], $registry->getTaggedIds('unknown'));
    }

    public function test_get_tagged_ids_returns_the_raw_ids_without_resolving(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->never())->method('get');

        $registry = new TagRegistry($container, ['my.tag' => ['service.a', 'service.b']]);

        $this->assertSame(['service.a', 'service.b'], $registry->getTaggedIds('my.tag'));
    }

    /**
     * @param array<string, mixed> $services
     */
    private function makeContainer(array $services): ContainerInterface
    {
        return new class($services) implements ContainerInterface {
            /** @param array<string, mixed> $services */
            public function __construct(private array $services) {}

            public function get(string $id): mixed
            {
                return $this->services[$id];
            }

            public function has(string $id): bool
            {
                return array_key_exists($id, $this->services);
            }
        };
    }
}
