<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MfAdminAccessCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_interactive_execution_never_creates_user(): void
    {
        $this->artisan('mf:admin-acesso', ['--no-interaction' => true])
            ->assertExitCode(Command::FAILURE);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_non_interactive_execution_never_resets_existing_admin(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'active' => true,
        ]);
        $originalHash = $admin->password;

        $this->artisan('mf:admin-acesso', ['--no-interaction' => true])
            ->assertExitCode(Command::FAILURE);

        $this->assertSame($originalHash, $admin->fresh()->password);
    }
}
