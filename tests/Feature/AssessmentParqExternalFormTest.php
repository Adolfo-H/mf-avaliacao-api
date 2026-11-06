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

final class AssessmentParqExternalFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_reference_can_be_registered_without_copying_questionnaire_or_claiming_clearance(): void
    {
        $evaluator = User::factory()->create(['role' => UserRole::Evaluator, 'active' => true]);
        $student = Student::create([
            'name' => 'Aluno fictício', 'birth_date' => '1992-01-01',
            'active' => true, 'created_by' => $evaluator->id, 'updated_by' => $evaluator->id,
        ]);
        $assessment = Assessment::create([
            'student_id' => $student->id, 'evaluator_id' => $evaluator->id,
            'evaluation_date' => '2026-11-02', 'status' => 'draft',
            'created_by' => $evaluator->id, 'updated_by' => $evaluator->id,
        ]);
        Sanctum::actingAs($evaluator);
        $url = "/api/v1/assessments/{$assessment->uuid}/anamnesis";
        $this->putJson($url, [
            'parq_external' => [
                'version' => '2025', 'completed_on' => '2026-11-02',
                'document_reference' => 'FORM-MF-001',
            ],
        ])->assertOk()
            ->assertJsonPath('data.parq.configured', false)
            ->assertJsonPath('data.parq.external_form_registered', true)
            ->assertJsonPath('data.parq.external_form_status', 'document_reference_only_no_clearance');

        $stored = $assessment->fresh()->anamnesis?->payload;
        $this->assertSame('FORM-MF-001', $stored['parq_external']['document_reference']);
        $this->assertSame($evaluator->id, $stored['parq_external']['recorded_by']);
        $this->assertNotEmpty($stored['parq_external']['recorded_at']);
        $this->assertArrayNotHasKey('cleared', $stored['parq_external']);

        $this->getJson($url)->assertOk()
            ->assertJsonPath('data.parq.external_form_registered', true);
    }

    public function test_does_not_accept_fabricated_clearance_or_incomplete_references(): void
    {
        $evaluator = User::factory()->create(['role' => UserRole::Evaluator, 'active' => true]);
        $student = Student::create([
            'name' => 'Aluno fictício 2', 'birth_date' => '1992-01-01',
            'active' => true, 'created_by' => $evaluator->id, 'updated_by' => $evaluator->id,
        ]);
        $assessment = Assessment::create([
            'student_id' => $student->id, 'evaluator_id' => $evaluator->id,
            'evaluation_date' => '2026-11-02', 'status' => 'draft',
            'created_by' => $evaluator->id, 'updated_by' => $evaluator->id,
        ]);
        Sanctum::actingAs($evaluator);
        $url = "/api/v1/assessments/{$assessment->uuid}/anamnesis";
        $this->putJson($url, [
            'parq_external' => [
                'version' => '2025', 'completed_on' => '2026-11-02',
                'document_reference' => 'FORM-MF-001', 'cleared' => true,
            ],
        ])->assertUnprocessable();
        $this->putJson($url, [
            'parq_external' => ['version' => '2025'],
        ])->assertUnprocessable();
        $this->assertNull($assessment->fresh()->anamnesis);
    }
}
