<?php

namespace Georgeff\Kernel\Test\Profiler;

use Georgeff\Kernel\Contract\DebuggableInterface;
use Georgeff\Kernel\Exception\ProfilerException;
use Georgeff\Kernel\Profiler\Profile;
use Georgeff\Kernel\Profiler\Profiler;
use PHPUnit\Framework\TestCase;

class ProfilerTest extends TestCase
{
    public function test_it_implements_debuggable_interface(): void
    {
        $profiler = new Profiler();

        $this->assertInstanceOf(DebuggableInterface::class, $profiler);
    }

    // -------------------------------------------------------------------------
    // initProfile() / hasProfile() / getProfile() / removeProfile()
    // -------------------------------------------------------------------------

    public function test_init_profile_returns_a_profile_instance(): void
    {
        $profiler = new Profiler();

        $this->assertInstanceOf(Profile::class, $profiler->initProfile('boot'));
    }

    public function test_init_profile_starts_the_profile(): void
    {
        $profiler = new Profiler();

        $profile = $profiler->initProfile('boot');

        $this->assertNotSame(-INF, $profile->getStartTime());
    }

    public function test_has_profile_returns_false_for_an_unknown_name(): void
    {
        $profiler = new Profiler();

        $this->assertFalse($profiler->hasProfile('boot'));
    }

    public function test_has_profile_returns_true_after_init_profile(): void
    {
        $profiler = new Profiler();
        $profiler->initProfile('boot');

        $this->assertTrue($profiler->hasProfile('boot'));
    }

    public function test_get_profile_returns_the_same_instance_returned_by_init_profile(): void
    {
        $profiler = new Profiler();
        $profile  = $profiler->initProfile('boot');

        $this->assertSame($profile, $profiler->getProfile('boot'));
    }

    public function test_get_profile_throws_for_an_unknown_profile(): void
    {
        $profiler = new Profiler();

        $this->expectException(ProfilerException::class);
        $this->expectExceptionMessage('Profile [boot] has not been initialized');

        $profiler->getProfile('boot');
    }

    public function test_remove_profile_removes_a_profile(): void
    {
        $profiler = new Profiler();
        $profiler->initProfile('boot');

        $profiler->removeProfile('boot');

        $this->assertFalse($profiler->hasProfile('boot'));
    }

    public function test_remove_profile_is_a_no_op_for_an_unknown_name(): void
    {
        $profiler = new Profiler();

        $profiler->removeProfile('boot');

        $this->assertFalse($profiler->hasProfile('boot'));
    }

    // -------------------------------------------------------------------------
    // register()
    // -------------------------------------------------------------------------

    public function test_register_uses_the_provided_name_as_the_debug_info_key(): void
    {
        $profiler  = new Profiler();
        $debuggable = new class implements DebuggableInterface {
            public function getDebugInfo(): array { return ['custom' => 'info']; }
        };

        $profiler->register($debuggable, 'my.service');

        $this->assertSame(['custom' => 'info'], $profiler->getDebugInfo()['components']['my.service']);
    }

    public function test_register_defaults_to_the_service_class_name_when_no_name_given(): void
    {
        $profiler   = new Profiler();
        $debuggable = new class implements DebuggableInterface {
            public function getDebugInfo(): array { return ['custom' => 'info']; }
        };

        $profiler->register($debuggable);

        $this->assertArrayHasKey($debuggable::class, $profiler->getDebugInfo()['components']);
    }

    public function test_register_overwrites_a_previous_registration_under_the_same_name(): void
    {
        $profiler = new Profiler();
        $first    = new class implements DebuggableInterface {
            public function getDebugInfo(): array { return ['which' => 'first']; }
        };
        $second   = new class implements DebuggableInterface {
            public function getDebugInfo(): array { return ['which' => 'second']; }
        };

        $profiler->register($first, 'my.service');
        $profiler->register($second, 'my.service');

        $this->assertSame(['which' => 'second'], $profiler->getDebugInfo()['components']['my.service']);
    }

    // -------------------------------------------------------------------------
    // getDebugInfo()
    // -------------------------------------------------------------------------

    public function test_get_debug_info_returns_an_empty_array_when_nothing_registered_or_profiled(): void
    {
        $profiler = new Profiler();

        $this->assertSame([], $profiler->getDebugInfo());
    }

    public function test_get_debug_info_omits_the_profiles_key_when_no_profile_has_been_initialized(): void
    {
        $profiler = new Profiler();
        $profiler->register(new class implements DebuggableInterface {
            public function getDebugInfo(): array { return []; }
        }, 'my.service');

        $this->assertArrayNotHasKey('profiles', $profiler->getDebugInfo());
    }

    public function test_get_debug_info_omits_the_components_key_when_nothing_has_been_registered(): void
    {
        $profiler = new Profiler();
        $profiler->initProfile('boot');

        $this->assertArrayNotHasKey('components', $profiler->getDebugInfo());
    }

    public function test_get_debug_info_includes_profiles_under_the_profiles_key_by_name(): void
    {
        $profiler = new Profiler();
        $profiler->initProfile('boot');

        $this->assertArrayHasKey('boot', $profiler->getDebugInfo()['profiles']);
    }

    public function test_get_debug_info_nests_registered_debuggables_under_the_components_key(): void
    {
        $profiler = new Profiler();
        $profiler->register(new class implements DebuggableInterface {
            public function getDebugInfo(): array { return ['loaded' => true]; }
        }, 'modules');

        $this->assertSame(['loaded' => true], $profiler->getDebugInfo()['components']['modules']);
    }

    public function test_get_debug_info_merges_multiple_profiles_and_registered_debuggables(): void
    {
        $profiler = new Profiler();
        $profiler->initProfile('boot');
        $profiler->initProfile('shutdown');
        $profiler->register(new class implements DebuggableInterface {
            public function getDebugInfo(): array { return ['loaded' => true]; }
        }, 'modules');

        $info = $profiler->getDebugInfo();

        $this->assertArrayHasKey('boot', $info['profiles']);
        $this->assertArrayHasKey('shutdown', $info['profiles']);
        $this->assertSame(['loaded' => true], $info['components']['modules']);
    }

    // -------------------------------------------------------------------------
    // getDebugInfo() — component sanitization
    // -------------------------------------------------------------------------

    public function test_get_debug_info_reduces_a_plain_object_to_its_class_name(): void
    {
        $object = new \stdClass();

        $profiler = new Profiler();
        $profiler->register(new class ($object) implements DebuggableInterface {
            public function __construct(private object $object) {}
            public function getDebugInfo(): array { return ['value' => $this->object]; }
        }, 'my.service');

        $this->assertSame(\stdClass::class, $profiler->getDebugInfo()['components']['my.service']['value']);
    }

    public function test_get_debug_info_reduces_a_closure_to_a_reference_id_string(): void
    {
        $closure = function () {};

        $profiler = new Profiler();
        $profiler->register(new class ($closure) implements DebuggableInterface {
            public function __construct(private \Closure $closure) {}
            public function getDebugInfo(): array { return ['value' => $this->closure]; }
        }, 'my.service');

        $value = $profiler->getDebugInfo()['components']['my.service']['value'];

        $this->assertIsString($value);
        $this->assertMatchesRegularExpression('/^Closure#\d+$/', $value);
    }

    public function test_get_debug_info_reduces_a_resource_to_its_resource_type(): void
    {
        $resource = fopen('php://memory', 'r');
        $this->assertIsResource($resource);

        $profiler = new Profiler();
        $profiler->register(new class ($resource) implements DebuggableInterface {
            /** @param resource $resource */
            public function __construct(private $resource) {}
            public function getDebugInfo(): array { return ['value' => $this->resource]; }
        }, 'my.service');

        $this->assertSame('stream', $profiler->getDebugInfo()['components']['my.service']['value']);

        fclose($resource);
    }

    public function test_get_debug_info_sanitizes_objects_nested_inside_arrays(): void
    {
        $object = new \stdClass();

        $profiler = new Profiler();
        $profiler->register(new class ($object) implements DebuggableInterface {
            public function __construct(private object $object) {}
            public function getDebugInfo(): array { return ['outer' => ['inner' => $this->object]]; }
        }, 'my.service');

        $this->assertSame(
            \stdClass::class,
            $profiler->getDebugInfo()['components']['my.service']['outer']['inner']
        );
    }

    public function test_get_debug_info_leaves_scalars_and_null_untouched(): void
    {
        $profiler = new Profiler();
        $profiler->register(new class implements DebuggableInterface {
            public function getDebugInfo(): array
            {
                return ['string' => 'value', 'int' => 1, 'float' => 1.5, 'bool' => true, 'null' => null];
            }
        }, 'my.service');

        $this->assertSame(
            ['string' => 'value', 'int' => 1, 'float' => 1.5, 'bool' => true, 'null' => null],
            $profiler->getDebugInfo()['components']['my.service']
        );
    }

    public function test_get_debug_info_reduces_a_backed_enum_to_its_value(): void
    {
        $profiler = new Profiler();
        $profiler->register(new class implements DebuggableInterface {
            public function getDebugInfo(): array { return ['value' => ProfilerTestBackedEnum::First]; }
        }, 'my.service');

        $this->assertSame('first', $profiler->getDebugInfo()['components']['my.service']['value']);
    }

    public function test_get_debug_info_reduces_a_pure_enum_to_its_name(): void
    {
        $profiler = new Profiler();
        $profiler->register(new class implements DebuggableInterface {
            public function getDebugInfo(): array { return ['value' => ProfilerTestPureEnum::First]; }
        }, 'my.service');

        $this->assertSame('First', $profiler->getDebugInfo()['components']['my.service']['value']);
    }

    public function test_get_debug_info_does_not_sanitize_the_profiles_key(): void
    {
        $profiler = new Profiler();
        $profile  = $profiler->initProfile('boot');
        $profile->stop();

        $duration = $profiler->getDebugInfo()['profiles']['boot']['duration'];

        $this->assertIsFloat($duration);
    }
}

enum ProfilerTestBackedEnum: string
{
    case First = 'first';
}

enum ProfilerTestPureEnum
{
    case First;
}
