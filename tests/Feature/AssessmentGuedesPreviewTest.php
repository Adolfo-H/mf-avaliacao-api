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

final class AssessmentGuedesPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_previews_saved_data_without_persisting_or_logging_clinical_values(): void
    {
        $admin = $this->user(UserRole::Admin);
        $evaluator = $this->user(UserRole::Evaluator);
        $assessment = $this->assessment($evaluator, 'male', '2001-02-01');
        $record = $assessment->bodyComposition()->create([
            'payload' => [
                'protocol' => 'guedes_3',
                'weight_kg' => 80,
                'skinfolds' => ['triceps' => 20, 'suprailiac' => 25, 'abdominal' => 15],
            ],
            'results' => ['bmi' => null, 'body_fat_percentage' => null],
            'updated_by' => $evaluator->id,
        ]);

        Sanctum::actingAs($admin);
        $response = $this->getJson($this->url($assessment))
            ->assertOk()
            ->assertJsonPath('data.preview.status', 'preview_available')
            ->assertJsonPath('data.preview.body_fat_percentage_preview', 20.48)
            ->assertJsonPath('data.preview.professionally_approved', false)
            ->assertJsonPath('data.preview.persisted', false);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertNull($record->fresh()->results['body_fat_percentage']);
        $log = \DB::table('audit_logs')->where('action', 'assessment.guedes_preview.view')->firstOrFail();
        $this->assertSame($assessment->id, $log->assessment_id);
        $this->assertStringNotContainsString('20.48', (string) $log->metadata);
        $this->assertStringNotContainsString('25', (string) $log->metadata);
        $this->assertStringNotContainsString('triceps', (string) $log->metadata);
    }

    public function test_female_previews_three_different_skinfold_sites(): void
    {
        $evaluator = $this->user(UserRole::Evaluator);
        $assessment = $this->assessment($evaluator, 'female', '2001-02-01');
        $assessment->bodyComposition()->create([
            'payload' => ['protocol' => 'guedes_3', 'skinfolds' => [
                'subscapular' => 12, 'suprailiac' => 16, 'thigh' => 22,
            ]],
            'results' => [],
            'updated_by' => $evaluator->id,
        ]);
        Sanctum::actingAs($evaluator);
        $this->getJson($this->url($assessment))
            ->assertOk()
            ->assertJsonPath('data.preview.status', 'preview_available')
            ->assertJsonPath('data.preview.body_fat_percentage_preview', 23);
    }

    public function test_existing_composition_fields_are_configured_for_guedes_without_homologation(): void
    {
        $evaluator = $this->user(UserRole::Evaluator);
        $assessment = $this->assessment($evaluator);
        Sanctum::actingAs($evaluator);
        $this->getJson("/api/v1/assessments/{$assessment->uuid}/body-composition")
            ->assertOk()
            ->assertJsonPath('data.protocols.2.key', 'guedes_3')
            ->assertJsonPath('data.protocols.2.fields_configured', true)
            ->assertJsonPath('data.protocols.2.calculation_configured', false);
    }

    public function test_guest_and_reception_are_denied(): void
    {
        $evaluator = $this->user(UserRole::Evaluator);
        $assessment = $this->assessment($evaluator);
        $this->getJson($this->url($assessment))->assertUnauthorized();
        Sanctum::actingAs($this->user(UserRole::Reception));
        $this->getJson($this->url($assessment))->assertForbidden();
    }

    public function test_evaluator_cannot_preview_others_assessments(): void
    {
        $assessment = $this->assessment($this->user(UserRole::Evaluator));
        Sanctum::actingAs($this->user(UserRole::Evaluator));
        $this->getJson($this->url($assessment))->assertForbidden();
    }

    public function test_missing_protocol_and_completed_assessment_behaviour(): void
    {
        $evaluator = $this->user(UserRole::Evaluator);
        $assessment = $this->assessment($evaluator);
        $assessment->update(['status' => 'completed', 'completed_at' => now()]);
        Sanctum::actingAs($evaluator);
        $this->getJson($this->url($assessment))
            ->assertOk()
            ->assertJsonPath('data.preview.status', 'protocol_not_selected')
            ->assertJsonPath('data.preview.body_fat_percentage_preview', null);
        $this->assertSame('completed', $assessment->fresh()->status->value);
    }

    private function url(Assessment $assessment): string
    {
        return "/api/v1/assessments/{$assessment->uuid}/body-composition/guedes-preview";
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role, 'active' => true]);
    }

    private function assessment(User $evaluator, string $sex = 'male', string $birthDate = '2001-02-01'): Assessment
    {
        $student = Student::create([
            'name' => 'Aluno Guedes (fictício)',
            'sex' => $sex,
            'birth_date' => $birthDate,
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
