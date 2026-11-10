<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class MfAdminAsEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_appears_in_selector_and_can_evaluate_with_own_account(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'active' => true]);
        $student = Student::create([
            'name' => 'Aluno Exemplo',
            'active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/assessment-evaluators')
            ->assertOk()
            ->assertJsonFragment(['id' => $admin->id, 'name' => $admin->name]);

        $response = $this->postJson('/api/v1/assessments', [
            'student_uuid' => $student->uuid,
            'evaluator_id' => $admin->id,
            'evaluation_date' => now()->subDay()->format('Y-m-d'),
        ])->assertCreated()->assertJsonPath('data.evaluator.id', $admin->id);

        $this->getJson('/api/v1/assessments/'.$response->json('data.uuid'))->assertOk();
        $this->assertSame(UserRole::Admin, $admin->fresh()->role);
    }

    public function test_admin_can_assign_himself_to_an_existing_assessment(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'active' => true]);
        $professional = User::factory()->create(['role' => UserRole::Evaluator, 'active' => true]);
        $student = Student::create(['name' => 'Aluno Exemplo 2', 'active' => true, 'created_by' => $admin->id, 'updated_by' => $admin->id]);
        Sanctum::actingAs($admin);

        $assessment = $this->postJson('/api/v1/assessments', [
            'student_uuid' => $student->uuid,
            'evaluator_id' => $professional->id,
            'evaluation_date' => now()->subDay()->format('Y-m-d'),
        ])->assertCreated();

        $this->putJson('/api/v1/assessments/'.$assessment->json('data.uuid'), [
            'evaluator_id' => $admin->id,
        ])->assertOk()->assertJsonPath('data.evaluator.id', $admin->id);
    }

    public function test_evaluator_does_not_gain_admin_assignment_permissions(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'active' => true]);
        $professional = User::factory()->create(['role' => UserRole::Evaluator, 'active' => true]);
        $student = Student::create(['name' => 'Aluno Exemplo 3', 'active' => true, 'created_by' => $admin->id, 'updated_by' => $admin->id]);
        Sanctum::actingAs($professional);

        $this->getJson('/api/v1/assessment-evaluators')
            ->assertOk()
            ->assertJsonMissing(['id' => $admin->id, 'name' => $admin->name]);

        $this->postJson('/api/v1/assessments', [
            'student_uuid' => $student->uuid,
            'evaluator_id' => $admin->id,
            'evaluation_date' => now()->subDay()->format('Y-m-d'),
        ])->assertForbidden();
    }
}
