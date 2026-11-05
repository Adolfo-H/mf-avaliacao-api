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

final class AssessmentRockportPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_views_saved_rockport_preview_without_writing_results_or_sensitive_audit_values(): void
    {
        $evaluator = $this->user(UserRole::Evaluator);
        $admin = $this->user(UserRole::Admin);
        $assessment = $this->assessment($evaluator, '1981-05-13', 'male');
        $vo2 = $assessment->vo2Max()->create([
            'payload' => [
                'protocol' => 'walk_1_mile',
                'test' => [
                    'distance_m' => 1609,
                    'time_seconds' => 900,
                    'heart_rate_bpm' => 130,
                    'weight_kg' => 80,
                ],
            ],
            'results' => ['vo2_max_ml_kg_min' => null, 'calculation_status' => 'pending_professional_validation'],
            'updated_by' => $evaluator->id,
        ]);

        Sanctum::actingAs($admin);
        $response = $this->getJson($this->url($assessment))
            ->assertOk()
            ->assertJsonPath('data.preview.status', 'preview_available')
            ->assertJsonPath('data.preview.estimated_vo2_ml_kg_min', 38.84)
            ->assertJsonPath('data.preview.professionally_approved', false)
            ->assertJsonPath('data.preview.persisted', false)
            ->assertJsonPath('data.preview.classification', null);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertNull($vo2->fresh()->results['vo2_max_ml_kg_min']);
        $entry = \App\Models\AuditLog::query()
            ->where('action', 'assessment.vo2_rockport_preview.view')
            ->firstOrFail();
        $this->assertSame($admin->id, $entry->user_id);
        $this->assertSame($assessment->id, $entry->assessment_id);
        $this->assertEqualsCanonicalizing(['formula_version', 'preview_status'], array_keys($entry->metadata));
        foreach (['1609', '38.84'] as $sensitiveValue) {
            $this->assertStringNotContainsString($sensitiveValue, (string) $entry->getRawOriginal('metadata'));
        }
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
        $this->assertDatabaseMissing('audit_logs', ['action' => 'assessment.vo2_rockport_preview.view']);
    }

    public function test_reception_is_forbidden(): void
    {
        $assessment = $this->assessment($this->user(UserRole::Evaluator));
        Sanctum::actingAs($this->user(UserRole::Reception));
        $this->getJson($this->url($assessment))->assertForbidden();
    }

    public function test_unauthenticated_user_is_rejected(): void
    {
        $assessment = $this->assessment($this->user(UserRole::Evaluator));
        $this->getJson($this->url($assessment))->assertUnauthorized();
    }

    public function test_missing_or_other_protocol_never_fabricates_an_estimate(): void
    {
        $evaluator = $this->user(UserRole::Evaluator);
        $assessment = $this->assessment($evaluator);
        Sanctum::actingAs($evaluator);
        $this->getJson($this->url($assessment))
            ->assertOk()
            ->assertJsonPath('data.preview.status', 'protocol_not_selected')
            ->assertJsonPath('data.preview.estimated_vo2_ml_kg_min', null);

        $assessment->vo2Max()->create([
            'payload' => ['protocol' => 'run_2400m', 'test' => ['distance_m' => 2400, 'time_seconds' => 900]],
            'results' => [],
            'updated_by' => $evaluator->id,
        ]);
        $this->getJson($this->url($assessment))
            ->assertOk()
            ->assertJsonPath('data.preview.status', 'pending_professional_validation')
            ->assertJsonPath('data.preview.estimated_vo2_ml_kg_min', null);
    }

    public function test_age_and_sex_come_from_student_on_assessment_date(): void
    {
        $evaluator = $this->user(UserRole::Evaluator);
        $assessment = $this->assessment($evaluator, '1999-01-01', 'other');
        $assessment->vo2Max()->create([
            'payload' => ['protocol' => 'walk_1_mile', 'test' => ['distance_m' => 1609, 'time_seconds' => 900, 'heart_rate_bpm' => 130, 'weight_kg' => 80]],
            'results' => [],
            'updated_by' => $evaluator->id,
        ]);
        Sanctum::actingAs($evaluator);
        $this->getJson($this->url($assessment))
            ->assertOk()
            ->assertJsonPath('data.preview.status', 'outside_original_age_range');

        $assessment->student->update(['birth_date' => '1981-05-13']);
        $this->getJson($this->url($assessment))
            ->assertOk()
            ->assertJsonPath('data.preview.status', 'sex_not_supported_by_equation');
    }

    private function url(Assessment $assessment): string
    {
        return "/api/v1/assessments/{$assessment->uuid}/vo2-max/rockport-preview";
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role, 'active' => true]);
    }

    private function assessment(User $evaluator, string $birthDate = '1981-05-13', string $sex = 'male'): Assessment
    {
        $student = Student::create([
            'name' => 'Aluno Exemplo Rockport',
            'birth_date' => $birthDate,
            'sex' => $sex,
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
