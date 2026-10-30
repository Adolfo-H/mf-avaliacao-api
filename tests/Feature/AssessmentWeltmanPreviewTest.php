<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Assessment;
use App\Models\AssessmentBodyComposition;
use App\Models\AuditLog;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class AssessmentWeltmanPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_records_private_measurements_and_reads_unvalidated_male_preview(): void
    {
        $admin = $this->user(UserRole::Admin);
        $evaluator = $this->user(UserRole::Evaluator);
        $assessment = $this->assessment($admin, $evaluator, 'male');
        Sanctum::actingAs($admin);

        $this->putJson($this->compositionUrl($assessment), $this->data())
            ->assertOk()
            ->assertJsonPath('data.results.body_fat_percentage', null)
            ->assertJsonPath('data.results.fat_mass_kg', null)
            ->assertJsonPath('data.body_composition.weltman.abdomen_upper_cm', 110);

        $response = $this->getJson($this->previewUrl($assessment))
            ->assertOk()
            ->assertJsonPath('data.status', 'estimated_unvalidated')
            ->assertJsonPath('data.protocol_selected', true)
            ->assertJsonPath('data.homologated', false)
            ->assertJsonPath('data.clinical_result_saved', false)
            ->assertJsonPath('data.calculated', true)
            ->assertJsonPath('data.inputs.mean_abdomen_cm', 112.5);
        $this->assertEqualsWithDelta(35.47, $response->json('data.percentage'), 0.005);
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));

        $raw = AssessmentBodyComposition::where('assessment_id', $assessment->id)
            ->firstOrFail()->getRawOriginal('payload');
        $this->assertStringNotContainsString('110', $raw);
        $this->assertStringNotContainsString('weltman', $raw);

        $log = AuditLog::where('action', 'assessment.weltman_preview.view')->firstOrFail();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($assessment->student_id, $log->student_id);
        $this->assertStringNotContainsString('112.5', json_encode($log->metadata));
        $this->assertStringNotContainsString('98', json_encode($log->metadata));
    }

    public function test_female_equation_uses_height_in_centimeters(): void
    {
        $admin = $this->user(UserRole::Admin);
        $assessment = $this->assessment($admin, $this->user(UserRole::Evaluator), 'female');
        Sanctum::actingAs($admin);
        $input = $this->data();
        $input['weight_kg'] = 95;
        $input['height_m'] = 1.65;
        $this->putJson($this->compositionUrl($assessment), $input)->assertOk();
        $this->getJson($this->previewUrl($assessment))
            ->assertOk()
            ->assertJsonPath('data.status', 'estimated_unvalidated')
            ->assertJsonPath('data.inputs.height_cm', 165)
            ->assertJsonPath('data.percentage', 47.98);
    }

    public function test_unconfirmed_applicability_does_not_return_a_calculation(): void
    {
        $admin = $this->user(UserRole::Admin);
        $assessment = $this->assessment($admin, $this->user(UserRole::Evaluator), 'male');
        Sanctum::actingAs($admin);
        $data = $this->data();
        $data['weltman']['applicability_confirmed'] = false;
        $this->putJson($this->compositionUrl($assessment), $data)->assertOk();
        $this->getJson($this->previewUrl($assessment))
            ->assertOk()
            ->assertJsonPath('data.status', 'applicability_not_confirmed')
            ->assertJsonPath('data.percentage', null);
    }

    public function test_different_protocol_and_missing_measurements_are_not_fabricated(): void
    {
        $admin = $this->user(UserRole::Admin);
        $assessment = $this->assessment($admin, $this->user(UserRole::Evaluator), 'male');
        Sanctum::actingAs($admin);
        $this->getJson($this->previewUrl($assessment))
            ->assertOk()
            ->assertJsonPath('data.status', 'protocol_not_selected');
        $this->putJson($this->compositionUrl($assessment), [
            'protocol' => 'weltman',
            'weight_kg' => 98,
            'height_m' => 1.75,
            'weltman' => ['applicability_confirmed' => true],
        ])->assertOk();
        $this->getJson($this->previewUrl($assessment))
            ->assertOk()
            ->assertJsonPath('data.status', 'missing_abdominal_measurements')
            ->assertJsonPath('data.percentage', null);
    }

    public function test_invalid_measurements_cannot_be_saved(): void
    {
        $admin = $this->user(UserRole::Admin);
        $assessment = $this->assessment($admin, $this->user(UserRole::Evaluator), 'female');
        Sanctum::actingAs($admin);
        $this->putJson($this->compositionUrl($assessment), [
            'protocol' => 'weltman',
            'weltman' => [
                'abdomen_upper_cm' => -1,
                'abdomen_umbilicus_cm' => 400,
                'applicability_confirmed' => 'not-a-boolean',
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'weltman.abdomen_upper_cm', 'weltman.abdomen_umbilicus_cm',
            'weltman.applicability_confirmed',
        ]);
        $this->assertDatabaseMissing('assessment_body_compositions', ['assessment_id' => $assessment->id]);
    }

    public function test_evaluator_cannot_see_another_professionals_assessment(): void
    {
        $admin = $this->user(UserRole::Admin);
        $owner = $this->user(UserRole::Evaluator);
        $outsider = $this->user(UserRole::Evaluator);
        $assessment = $this->assessment($admin, $owner, 'male');
        Sanctum::actingAs($outsider);
        $this->getJson($this->previewUrl($assessment))->assertForbidden();
        $this->putJson($this->compositionUrl($assessment), $this->data())->assertForbidden();
        Sanctum::actingAs($owner);
        $this->getJson($this->previewUrl($assessment))->assertOk();
    }

    public function test_reception_and_guest_have_no_clinical_access(): void
    {
        $admin = $this->user(UserRole::Admin);
        $assessment = $this->assessment($admin, $this->user(UserRole::Evaluator), 'male');
        $this->getJson($this->previewUrl($assessment))->assertUnauthorized();
        Sanctum::actingAs($this->user(UserRole::Reception));
        $this->getJson($this->previewUrl($assessment))->assertForbidden();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'assessment.weltman_preview.view']);
    }

    public function test_no_equation_for_unspecified_sex_or_minors(): void
    {
        $admin = $this->user(UserRole::Admin);
        $assessment = $this->assessment($admin, $this->user(UserRole::Evaluator), 'not_informed');
        Sanctum::actingAs($admin);
        $this->putJson($this->compositionUrl($assessment), $this->data())->assertOk();
        $this->getJson($this->previewUrl($assessment))
            ->assertOk()->assertJsonPath('data.status', 'sex_not_supported');
        $assessment->student->update(['sex' => 'male', 'birth_date' => '2013-01-01']);
        $this->getJson($this->previewUrl($assessment))
            ->assertOk()->assertJsonPath('data.status', 'adult_required');
    }

    public function test_completed_assessment_cannot_be_rewritten(): void
    {
        $admin = $this->user(UserRole::Admin);
        $assessment = $this->assessment($admin, $this->user(UserRole::Evaluator), 'male', true);
        Sanctum::actingAs($admin);
        $this->putJson($this->compositionUrl($assessment), $this->data())->assertUnprocessable();
    }

    /** @return array<string,mixed> */
    private function data(): array
    {
        return [
            'protocol' => 'weltman',
            'weight_kg' => 98,
            'height_m' => 1.75,
            'weltman' => [
                'abdomen_upper_cm' => 110,
                'abdomen_umbilicus_cm' => 115,
                'applicability_confirmed' => true,
            ],
        ];
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role, 'active' => true]);
    }

    private function assessment(User $creator, User $evaluator, string $sex, bool $completed = false): Assessment
    {
        $student = Student::create([
            'name' => 'Aluno Fictício Weltman',
            'birth_date' => '1982-03-01',
            'sex' => $sex,
            'active' => true,
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
        ]);
        return Assessment::create([
            'student_id' => $student->id,
            'evaluator_id' => $evaluator->id,
            'evaluation_date' => '2026-10-29',
            'status' => $completed ? 'completed' : 'draft',
            'completed_at' => $completed ? now() : null,
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
        ]);
    }

    private function compositionUrl(Assessment $assessment): string
    {
        return "/api/v1/assessments/{$assessment->uuid}/body-composition";
    }

    private function previewUrl(Assessment $assessment): string
    {
        return "/api/v1/assessments/{$assessment->uuid}/body-composition/weltman-preview";
    }
}
