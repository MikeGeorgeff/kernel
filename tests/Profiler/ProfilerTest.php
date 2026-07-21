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
}
