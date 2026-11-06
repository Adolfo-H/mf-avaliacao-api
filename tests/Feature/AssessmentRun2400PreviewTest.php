<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class AssessmentRun2400PreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_preview_is_private_does_not_persist_clinical_result_and_audits_only_version(): void
    {
        $evaluator = $this->createUser(UserRole::Evaluator);
        $admin = $this->createUser(UserRole::Admin);
        $assessment = $this->makeAssessment($evaluator);
        $vo2 = $assessment->vo2Max()->create([
            'payload' => [
                'protocol' => 'run_2400m',
                'test' => ['distance_m' => 2400, 'time_seconds' => 720],
            ],
            'results' => [
                'vo2_max_ml_kg_min' => null,
                'calculation_status' => 'pending_professional_validation',
            ],
            'updated_by' => $evaluator->id,
        ]);
        $beforePayload = $vo2->getRawOriginal('payload');
        $beforeResults = $vo2->getRawOriginal('results');

        Sanctum::actingAs($admin);
        $response = $this->getJson($this->url($assessment))
            ->assertOk()
            ->assertJsonPath('data.preview.status', 'preview_available')
            ->assertJsonPath('data.preview.estimated_vo2_ml_kg_min', 43.75)
            ->assertJsonPath('data.preview.professionally_approved', false)
            ->assertJsonPath('data.preview.persisted', false)
            ->assertJsonPath('data.preview.classification', null);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame($beforePayload, $vo2->fresh()->getRawOriginal('payload'));
        $this->assertSame($beforeResults, $vo2->fresh()->getRawOriginal('results'));
        $audit = AuditLog::query()->where('action', 'assessment.vo2_2400_preview.view')->firstOrFail();
        $this->assertSame($admin->id, $audit->user_id);
        $this->assertEqualsCanonicalizing(['formula_version', 'preview_status'], array_keys((array) $audit->metadata));
    }

    public function test_evaluator_cannot_read_another_evaluators_data(): void
    {
        $owner = $this->createUser(UserRole::Evaluator);
        $assessment = $this->makeAssessment($owner);
        Sanctum::actingAs($this->createUser(UserRole::Evaluator));
        $this->getJson($this->url($assessment))->assertForbidden();
    }

    public function test_reception_and_unauthenticated_users_are_denied(): void
    {
        $assessment = $this->makeAssessment($this->createUser(UserRole::Evaluator));
        $this->getJson($this->url($assessment))->assertUnauthorized();
        Sanctum::actingAs($this->createUser(UserRole::Reception));
        $this->getJson($this->url($assessment))->assertForbidden();
    }

    public function test_missing_saved_measurements_do_not_fabricate_estimate(): void
    {
        $evaluator = $this->createUser(UserRole::Evaluator);
        $assessment = $this->makeAssessment($evaluator);
        Sanctum::actingAs($evaluator);
        $this->getJson($this->url($assessment))
            ->assertOk()
            ->assertJsonPath('data.preview.status', 'protocol_not_selected')
            ->assertJsonPath('data.preview.estimated_vo2_ml_kg_min', null);
    }

    private function createUser(UserRole $role): User
    {
        return User::factory()->create(['role' => $role, 'active' => true]);
    }

    private function makeAssessment(User $evaluator): Assessment
    {
        $student = Student::create([
            'name' => 'Aluno de teste 2,4 km',
            'birth_date' => '1990-04-10',
            'sex' => 'male',
            'active' => true,
            'created_by' => $evaluator->id,
            'updated_by' => $evaluator->id,
        ]);
        return Assessment::create([
            'student_id' => $student->id,
            'evaluator_id' => $evaluator->id,
            'evaluation_date' => '2026-10-01',
            'status' => 'draft',
            'created_by' => $evaluator->id,
            'updated_by' => $evaluator->id,
        ]);
    }

    private function url(Assessment $assessment): string
    {
        return "/api/v1/assessments/{$assessment->uuid}/vo2-max/2400m-preview";
    }
}
