<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Assessment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class AssessmentCooperPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_reads_saved_values_without_persisting_preview_or_exposing_values_in_audit(): void
    {
        $admin = $this->user(UserRole::Admin);
        $evaluator = $this->user(UserRole::Evaluator);
        $assessment = $this->assessment($evaluator);
        $entry = $assessment->vo2Max()->create([
            'payload' => [
                'protocol' => 'cooper_12_min',
                'test' => ['distance_m' => 2400, 'time_seconds' => 720],
            ],
            'results' => ['vo2_max_ml_kg_min' => null, 'calculation_status' => 'pending_professional_validation'],
            'updated_by' => $evaluator->id,
        ]);

        Sanctum::actingAs($admin);
        $response = $this->getJson($this->url($assessment))
            ->assertOk()
            ->assertJsonPath('data.preview.status', 'preview_available')
            ->assertJsonPath('data.preview.estimated_vo2_ml_kg_min', 42.35)
            ->assertJsonPath('data.preview.professionally_approved', false)
            ->assertJsonPath('data.preview.persisted', false)
            ->assertJsonPath('data.preview.classification', null);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertNull($entry->fresh()->results['vo2_max_ml_kg_min']);
        $log = \DB::table('audit_logs')
            ->where('action', 'assessment.vo2_cooper_preview.view')
            ->firstOrFail();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($assessment->id, $log->assessment_id);
        $this->assertStringNotContainsString('2400', (string) $log->metadata);
        $this->assertStringNotContainsString('42.35', (string) $log->metadata);
    }

    public function test_evaluator_can_read_own_assessment(): void
    {
        $evaluator = $this->user(UserRole::Evaluator);
        $assessment = $this->assessment($evaluator);
        Sanctum::actingAs($evaluator);
        $this->getJson($this->url($assessment))->assertOk();
    }

    public function test_evaluator_cannot_read_another_evaluators_assessment(): void
    {
        $assessment = $this->assessment($this->user(UserRole::Evaluator));
        Sanctum::actingAs($this->user(UserRole::Evaluator));
        $this->getJson($this->url($assessment))->assertForbidden();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'assessment.vo2_cooper_preview.view']);
    }

    public function test_reception_is_forbidden(): void
    {
        $assessment = $this->assessment($this->user(UserRole::Evaluator));
        Sanctum::actingAs($this->user(UserRole::Reception));
        $this->getJson($this->url($assessment))->assertForbidden();
    }

    public function test_guest_is_unauthorized(): void
    {
        $assessment = $this->assessment($this->user(UserRole::Evaluator));
        $this->getJson($this->url($assessment))->assertUnauthorized();
    }

    public function test_no_saved_test_or_unsupported_protocol_has_no_estimate(): void
    {
        $evaluator = $this->user(UserRole::Evaluator);
        $assessment = $this->assessment($evaluator);
        Sanctum::actingAs($evaluator);
        $this->getJson($this->url($assessment))
            ->assertOk()
            ->assertJsonPath('data.preview.status', 'protocol_not_selected')
            ->assertJsonPath('data.preview.estimated_vo2_ml_kg_min', null);

        $assessment->vo2Max()->create([
            'payload' => ['protocol' => 'run_2400m', 'test' => ['distance_m' => 2400, 'time_seconds' => 720]],
            'results' => [],
            'updated_by' => $evaluator->id,
        ]);
        $this->getJson($this->url($assessment))
            ->assertOk()
            ->assertJsonPath('data.preview.status', 'pending_professional_validation')
            ->assertJsonPath('data.preview.estimated_vo2_ml_kg_min', null);
    }

    public function test_invalid_saved_duration_is_not_used_for_cooper(): void
    {
        $evaluator = $this->user(UserRole::Evaluator);
        $assessment = $this->assessment($evaluator);
        $assessment->vo2Max()->create([
            'payload' => ['protocol' => 'cooper_12_min', 'test' => ['distance_m' => 2400, 'time_seconds' => 600]],
            'results' => [],
            'updated_by' => $evaluator->id,
        ]);
        Sanctum::actingAs($evaluator);
        $this->getJson($this->url($assessment))
            ->assertOk()
            ->assertJsonPath('data.preview.status', 'invalid_duration')
            ->assertJsonPath('data.preview.estimated_vo2_ml_kg_min', null);
    }

    private function url(Assessment $assessment): string
    {
        return "/api/v1/assessments/{$assessment->uuid}/vo2-max/cooper-preview";
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role, 'active' => true]);
    }

    private function assessment(User $evaluator): Assessment
    {
        $student = Student::create([
            'name' => 'Aluno Exemplo Cooper',
            'birth_date' => '1992-05-11',
            'sex' => 'male',
            'active' => true,
            'created_by' => $evaluator->id,
            'updated_by' => $evaluator->id,
        ]);
        return Assessment::create([
            'student_id' => $student->id,
            'evaluator_id' => $evaluator->id,
            'evaluation_date' => '2026-06-20',
            'status' => 'draft',
            'created_by' => $evaluator->id,
            'updated_by' => $evaluator->id,
        ]);
    }
}
