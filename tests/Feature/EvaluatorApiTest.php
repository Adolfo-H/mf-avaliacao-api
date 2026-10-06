<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EvaluatorApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_profile_using_mobile_contract(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'active' => true,
        ]);

        $evaluator = User::factory()->create([
            'role' => UserRole::Evaluator,
            'active' => true,
        ]);

        $evaluator->evaluatorProfile()->create([
            'phone' => '(45) 99999-1111',
            'professional_registration' => 'CREF-123',
            'specialty' => 'Avaliação Física',
            'company_name' => 'MF',
            'active' => true,
        ]);

        Sanctum::actingAs($admin);

        $response = $this
            ->getJson("/api/v1/evaluators/{$evaluator->id}")
            ->assertOk()
            ->assertJsonPath(
                'data.profile.phone',
                '(45) 99999-1111'
            )
            ->assertJsonPath(
                'data.profile.professional_registration',
                'CREF-123'
            )
            ->assertJsonPath(
                'data.profile.specialty',
                'Avaliação Física'
            )
            ->assertJsonPath(
                'data.profile.company_name',
                'MF'
            );

        $this->assertArrayNotHasKey(
            'evaluator_profile',
            $response->json('data')
        );
    }

    public function test_updating_only_name_preserves_profile_fields(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'active' => true,
        ]);

        $evaluator = User::factory()->create([
            'role' => UserRole::Evaluator,
            'active' => true,
        ]);

        $evaluator->evaluatorProfile()->create([
            'phone' => '(45) 99999-1111',
            'professional_registration' => 'CREF-123',
            'specialty' => 'Avaliação Física',
            'company_name' => 'MF',
            'active' => true,
        ]);

        Sanctum::actingAs($admin);

        $this
            ->putJson(
                "/api/v1/evaluators/{$evaluator->id}",
                [
                    'name' => 'Nome Atualizado',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Nome Atualizado'
            );

        $this->assertDatabaseHas(
            'evaluator_profiles',
            [
                'user_id' => $evaluator->id,
                'phone' => '(45) 99999-1111',
                'professional_registration' => 'CREF-123',
                'specialty' => 'Avaliação Física',
                'company_name' => 'MF',
            ]
        );
    }
}
