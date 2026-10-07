<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_blocked_after_five_invalid_attempts(): void
    {
        $user =
            $this->createUser();

        $ip =
            '10.10.10.10';

        $key =
            $this->rateLimitKey(
                $user->email,
                $ip
            );

        RateLimiter::clear($key);

        for (
            $attempt = 1;
            $attempt <= 5;
            $attempt++
        ) {
            $this
                ->withServerVariables([
                    'REMOTE_ADDR' =>
                        $ip,
                ])
                ->postJson(
                    '/api/v1/auth/login',
                    [
                        'email' =>
                            $user->email,

                        'password' =>
                            'senha-errada',

                        'device_name' =>
                            'teste-rate-limit',
                    ]
                )
                ->assertStatus(422)
                ->assertJsonValidationErrors(
                    'email'
                );
        }

        $this
            ->withServerVariables([
                'REMOTE_ADDR' =>
                    $ip,
            ])
            ->postJson(
                '/api/v1/auth/login',
                [
                    'email' =>
                        $user->email,

                    'password' =>
                        'senha-errada',

                    'device_name' =>
                        'teste-rate-limit',
                ]
            )
            ->assertStatus(429)
            ->assertJsonPath(
                'message',
                'Muitas tentativas de login. Aguarde antes de tentar novamente.'
            );

        RateLimiter::clear($key);
    }

    public function test_successful_login_clears_previous_failed_attempts(): void
    {
        $user =
            $this->createUser();

        $ip =
            '10.10.10.11';

        $key =
            $this->rateLimitKey(
                $user->email,
                $ip
            );

        RateLimiter::clear($key);

        for (
            $attempt = 1;
            $attempt <= 4;
            $attempt++
        ) {
            $this
                ->withServerVariables([
                    'REMOTE_ADDR' =>
                        $ip,
                ])
                ->postJson(
                    '/api/v1/auth/login',
                    [
                        'email' =>
                            $user->email,

                        'password' =>
                            'senha-errada',

                        'device_name' =>
                            'teste-rate-limit',
                    ]
                )
                ->assertStatus(422);
        }

        $this
            ->withServerVariables([
                'REMOTE_ADDR' =>
                    $ip,
            ])
            ->postJson(
                '/api/v1/auth/login',
                [
                    'email' =>
                        $user->email,

                    'password' =>
                        'Senha@123',

                    'device_name' =>
                        'teste-rate-limit',
                ]
            )
            ->assertOk()
            ->assertJsonStructure([
                'token',
                'token_type',
                'user',
            ]);

        $this->assertSame(
            0,
            RateLimiter::attempts(
                $key
            )
        );

        RateLimiter::clear($key);
    }

    private function createUser(): User
    {
        return User::factory()->create([
            'email' =>
                'rate.limit@mf.local',

            'password' =>
                'Senha@123',

            'role' =>
                UserRole::Evaluator,

            'active' => true,
        ]);
    }

    private function rateLimitKey(
        string $email,
        string $ip
    ): string {
        return
            'login:'
            . Str::lower($email)
            . '|'
            . $ip;
    }
}
