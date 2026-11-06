<?php

namespace Tests\Feature;

use Tests\TestCase;

final class MfReleaseCheckCommandTest extends TestCase
{
    public function test_diagnostic_does_not_block_a_local_development_environment(): void
    {
        config()->set('app.env', 'local');
        config()->set('app.debug', true);
        config()->set('app.url', 'http://localhost:8000');

        $this->artisan('mf:release-check')->assertExitCode(0);
    }

    public function test_strict_mode_refuses_debug_and_insecure_local_url(): void
    {
        config()->set('app.env', 'local');
        config()->set('app.debug', true);
        config()->set('app.url', 'http://localhost:8000');

        $this->artisan('mf:release-check', ['--strict' => true])->assertExitCode(1);
    }

    public function test_strict_mode_refuses_missing_application_key(): void
    {
        config()->set('app.env', 'production');
        config()->set('app.debug', false);
        config()->set('app.url', 'https://api.mf.example.org');
        config()->set('app.key', '');

        $this->artisan('mf:release-check', ['--strict' => true])->assertExitCode(1);
    }

    public function test_strict_mode_refuses_public_photo_disk(): void
    {
        config()->set('app.env', 'production');
        config()->set('app.debug', false);
        config()->set('app.url', 'https://api.mf.example.org');
        config()->set('app.key', 'base64:'.str_repeat('A', 44));
        config()->set('student-photos.disk', 'public');

        $this->artisan('mf:release-check', ['--strict' => true])->assertExitCode(1);
    }
}
