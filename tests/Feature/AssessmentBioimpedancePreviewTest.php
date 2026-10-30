<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Assessment;
use App\Models\AssessmentBodyComposition;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class AssessmentBioimpedancePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_record_device_values_and_view_private_preview(): void
    {
        $admin = $this->user(UserRole::Admin);
        $evaluator = $this->user(UserRole::Evaluator);
        $assessment = $this->assessment($admin, $evaluator);
        Sanctum::actingAs($admin);

        $this->putJson($this->compositionUrl($assessment), [
            'protocol' => 'bioimpedance',
            'weight_kg' => 78.5,
            'height_m' => 1.78,
            'bioimpedance' => [
                'manufacturer' => 'Equipamento Demonstrativo',
                'model' => 'Modelo Exemplo',
                'measured_on' => '2026-10-29',
                'reported_weight_kg' => 78.5,
                'body_fat_percentage' => 20.3,
                'fat_mass_kg' => 15.9,
                'resistance_ohm' => 520.4,
                'conditions' => 'Teste de registro sem dados pessoais',
            ],
        ])->assertOk()
            ->assertJsonPath('data.body_composition.bioimpedance.body_fat_percentage', 20.3)
            ->assertJsonPath('data.results.body_fat_percentage', null)
            ->assertJsonPath('data.results.fat_mass_kg', null);

        $response = $this->getJson($this->previewUrl($assessment))
            ->assertOk()
            ->assertJsonPath('data.protocol_selected', true)
            ->assertJsonPath('data.homologated', false)
            ->assertJsonPath('data.calculation_performed', false)
            ->assertJsonPath('data.source', 'reported_by_device')
            ->assertJsonPath('data.device.manufacturer', 'Equipamento Demonstrativo')
            ->assertJsonPath('data.measurements.1.key', 'body_fat_percentage')
            ->assertJsonPath('data.measurements.1.value', 20.3)
            ->assertJsonPath('data.measurements.6.value', 520.4);

        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $raw = AssessmentBodyComposition::query()->where('assessment_id', $assessment->id)
            ->firstOrFail()->getRawOriginal('payload');
        $this->assertStringNotContainsString('Equipamento Demonstrativo', $raw);
        $this->assertStringNotContainsString('520.4', $raw);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'assessment.bioimpedance_preview.view',
            'assessment_id' => $assessment->id,
            'student_id' => $assessment->student_id,
            'user_id' => $admin->id,
        ]);
        $log = \App\Models\AuditLog::query()
            ->where('action', 'assessment.bioimpedance_preview.view')->firstOrFail();
        $this->assertStringNotContainsString('520.4', json_encode($log->metadata));
        $this->assertStringNotContainsString('Equipamento Demonstrativo', json_encode($log->metadata));
    }

    public function test_missing_values_are_null_and_not_invented(): void
    {
        $admin = $this->user(UserRole::Admin);
        $assessment = $this->assessment($admin, $this->user(UserRole::Evaluator));
        Sanctum::actingAs($admin);

        $this->getJson($this->previewUrl($assessment))->assertOk()
            ->assertJsonPath('data.protocol_selected', false)
            ->assertJsonPath('data.status', 'protocol_not_selected')
            ->assertJsonPath('data.measurements.1.value', null);

        $this->putJson($this->compositionUrl($assessment), [
            'protocol' => 'bioimpedance',
        ])->assertOk();
        $this->getJson($this->previewUrl($assessment))->assertOk()
            ->assertJsonPath('data.status', 'no_measurements')
            ->assertJsonPath('data.measurements.1.value', null);
    }

    public function test_server_rejects_out_of_range_device_values(): void
    {
        $admin = $this->user(UserRole::Admin);
        $assessment = $this->assessment($admin, $this->user(UserRole::Evaluator));
        Sanctum::actingAs($admin);
        $this->putJson($this->compositionUrl($assessment), [
            'protocol' => 'bioimpedance',
            'bioimpedance' => [
                'body_fat_percentage' => 105,
                'reported_weight_kg' => -5,
                'measured_on' => 'not-a-date',
                'resistance_ohm' => -10,
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'bioimpedance.body_fat_percentage',
                'bioimpedance.reported_weight_kg',
                'bioimpedance.measured_on',
                'bioimpedance.resistance_ohm',
            ]);
        $this->assertDatabaseMissing('assessment_body_compositions', ['assessment_id' => $assessment->id]);
    }

    public function test_evaluator_only_accesses_own_assessment(): void
    {
        $owner = $this->user(UserRole::Evaluator);
        $other = $this->user(UserRole::Evaluator);
        $admin = $this->user(UserRole::Admin);
        $assessment = $this->assessment($admin, $owner);
        Sanctum::actingAs($other);
        $this->getJson($this->previewUrl($assessment))->assertForbidden();
        $this->putJson($this->compositionUrl($assessment), [
            'protocol' => 'bioimpedance',
        ])->assertForbidden();
        Sanctum::actingAs($owner);
        $this->getJson($this->previewUrl($assessment))->assertOk();
    }

    public function test_reception_and_guest_cannot_access_bioimpedance(): void
    {
        $admin = $this->user(UserRole::Admin);
        $assessment = $this->assessment($admin, $this->user(UserRole::Evaluator));
        $this->getJson($this->previewUrl($assessment))->assertUnauthorized();
        Sanctum::actingAs($this->user(UserRole::Reception));
        $this->getJson($this->previewUrl($assessment))->assertForbidden();
    }

    public function test_completed_assessment_blocks_device_changes(): void
    {
        $admin = $this->user(UserRole::Admin);
        $assessment = $this->assessment($admin, $this->user(UserRole::Evaluator), true);
        Sanctum::actingAs($admin);
        $this->putJson($this->compositionUrl($assessment), [
            'protocol' => 'bioimpedance',
            'bioimpedance' => ['body_fat_percentage' => 21.7],
        ])->assertUnprocessable();
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role, 'active' => true]);
    }

    private function assessment(User $creator, User $evaluator, bool $completed = false): Assessment
    {
        $student = Student::create([
            'name' => 'Aluno Fictício Bioimpedância',
            'birth_date' => '1994-06-12',
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
        return "/api/v1/assessments/{$assessment->uuid}/body-composition/bioimpedance-preview";
    }
}
