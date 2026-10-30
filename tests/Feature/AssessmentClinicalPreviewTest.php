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

final class AssessmentClinicalPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_preview_without_saving_or_exposing_values_in_audit(): void
    {
        $admin = $this->user(UserRole::Admin);
        $evaluator = $this->user(UserRole::Evaluator);
        $assessment = $this->assessment($evaluator, 'male', '1986-02-01');
        $composition = $assessment->bodyComposition()->create([
            'payload' => [
                'protocol' => 'pollock_7', 'skinfolds' => [
                    'chest' => 12, 'midaxillary' => 10, 'triceps' => 8,
                    'subscapular' => 12, 'abdominal' => 15,
                    'suprailiac' => 11, 'thigh' => 18,
                ],
            ],
            'results' => ['body_fat_percentage' => null],
            'updated_by' => $evaluator->id,
        ]);

        Sanctum::actingAs($admin);
        $response = $this->getJson($this->url($assessment))
            ->assertOk()
            ->assertJsonPath('data.preview.status', 'preview_available')
            ->assertJsonPath('data.preview.professionally_approved', false)
            ->assertJsonPath('data.preview.persisted', false)
            ->assertJsonPath('data.preview.body_fat_percentage_preview', 13.86);

        $this->assertNull($composition->fresh()->results['body_fat_percentage']);
        $raw = \DB::table('audit_logs')
            ->where('action', 'assessment.clinical_preview.view')
            ->firstOrFail();
        $this->assertSame($assessment->id, $raw->assessment_id);
        $this->assertStringNotContainsString('13.86', (string) $raw->metadata);
        $this->assertStringNotContainsString('abdominal', (string) $raw->metadata);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_female_three_site_uses_only_three_required_measurements(): void
    {
        $evaluator = $this->user(UserRole::Evaluator);
        $assessment = $this->assessment($evaluator, 'female', '1999-01-01');
        $assessment->bodyComposition()->create([
            'payload' => [
                'protocol' => 'pollock_3',
                'skinfolds' => [
                    'triceps' => 16, 'suprailiac' => 15, 'thigh' => 20,
                ],
            ],
            'results' => [],
            'updated_by' => $evaluator->id,
        ]);

        Sanctum::actingAs($evaluator);
        $this->getJson($this->url($assessment))
            ->assertOk()
            ->assertJsonPath('data.preview.status', 'preview_available')
            ->assertJsonPath('data.preview.required_skinfolds', ['triceps', 'suprailiac', 'thigh']);
    }

    public function test_reception_is_forbidden(): void
    {
        $evaluator = $this->user(UserRole::Evaluator);
        $assessment = $this->assessment($evaluator);
        Sanctum::actingAs($this->user(UserRole::Reception));
        $this->getJson($this->url($assessment))->assertForbidden();
    }

    public function test_evaluator_cannot_access_another_evaluators_assessment(): void
    {
        $assessment = $this->assessment($this->user(UserRole::Evaluator));
        Sanctum::actingAs($this->user(UserRole::Evaluator));
        $this->getJson($this->url($assessment))->assertForbidden();
    }

    public function test_guest_is_unauthorized(): void
    {
        $assessment = $this->assessment($this->user(UserRole::Evaluator));
        $this->getJson($this->url($assessment))->assertUnauthorized();
    }

    public function test_missing_data_return_pending_preview_instead_of_fabricated_values(): void
    {
        $admin = $this->user(UserRole::Admin);
        $assessment = $this->assessment($this->user(UserRole::Evaluator));
        Sanctum::actingAs($admin);
        $this->getJson($this->url($assessment))
            ->assertOk()
            ->assertJsonPath('data.preview.status', 'protocol_not_selected')
            ->assertJsonPath('data.preview.body_density_preview', null);
    }

    private function url(Assessment $assessment): string
    {
        return "/api/v1/assessments/{$assessment->uuid}/body-composition/clinical-preview";
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role, 'active' => true]);
    }

    private function assessment(User $evaluator, string $sex = 'male', string $birth = '1990-01-01'): Assessment
    {
        $student = Student::create([
            'name' => 'Aluno Teste Prévia',
            'sex' => $sex,
            'birth_date' => $birth,
            'active' => true,
            'created_by' => $evaluator->id,
            'updated_by' => $evaluator->id,
        ]);
        return Assessment::create([
            'student_id' => $student->id,
            'evaluator_id' => $evaluator->id,
            'evaluation_date' => '2026-06-01',
            'status' => 'draft',
            'created_by' => $evaluator->id,
            'updated_by' => $evaluator->id,
        ]);
    }
}
