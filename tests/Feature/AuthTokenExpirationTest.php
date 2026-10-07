<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTokenExpirationTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_token_can_access_authenticated_route(): void
    {
        config()->set(
            'sanctum.expiration',
            60
        );

        $user =
            User::factory()->create([
                'role' =>
                    UserRole::Evaluator,

                'active' => true,
            ]);

        $token =
            $user
                ->createToken(
                    'teste-expiracao'
                )
                ->plainTextToken;

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/auth/me'
            )
            ->assertOk()
            ->assertJsonPath(
                'user.id',
                $user->id
            );
    }

    public function test_expired_token_cannot_access_authenticated_route(): void
    {
        config()->set(
            'sanctum.expiration',
            60
        );

        $user =
            User::factory()->create([
                'role' =>
                    UserRole::Evaluator,

                'active' => true,
            ]);

        $token =
            $user
                ->createToken(
                    'teste-expiracao'
                )
                ->plainTextToken;

        $this->travel(
            61
        )->minutes();

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/auth/me'
            )
            ->assertUnauthorized();
    }
}
