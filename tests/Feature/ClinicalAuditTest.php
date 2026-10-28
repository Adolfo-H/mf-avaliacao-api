<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClinicalAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_body_composition_changes_are_encrypted_and_no_op_does_not_log(): void
    {
        $assessment = $this->assessment();

        $this->putJson($this->url($assessment, 'body-composition'), [
            'weight_kg' => 80.5,
            'height_m' => 1.75,
        ])->assertOk();

        $this->putJson($this->url($assessment, 'body-composition'), [
            'weight_kg' => 79.2,
        ])->assertOk();

        $log = $this->clinicalLog($assessment, 'body_composition');

        $this->assertContains('weight_kg', $log->metadata['changed_fields']);
        $this->assertNotContains('height_m', $log->metadata['changed_fields']);

        $rawMetadata = $log->getRawOriginal('metadata');
        $this->assertStringNotContainsString('79.2', $rawMetadata);

        $changes = json_decode(
            Crypt::decryptString($log->metadata['changes_encrypted']),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        $this->assertEquals(80.5, $changes['weight_kg']['old']);
        $this->assertEquals(79.2, $changes['weight_kg']['new']);

        $previousCount = $this->clinicalCount($assessment);
        $this->putJson($this->url($assessment, 'body-composition'), [
            'weight_kg' => 79.2,
        ])->assertOk();

        $this->assertSame($previousCount, $this->clinicalCount($assessment));
    }

    public function test_anthropometry_tracks_nested_fields(): void
    {
        $assessment = $this->assessment();

        $this->putJson($this->url($assessment, 'anthropometry'), [
            'circumferences' => [
                'waist' => 83.4,
                'hip' => 102,
            ],
        ])->assertOk();

        $this->putJson($this->url($assessment, 'anthropometry'), [
            'circumferences' => [
                'waist' => 82,
            ],
        ])->assertOk();

        $log = $this->clinicalLog($assessment, 'circumferences');

        $this->assertSame(
            ['circumferences.waist'],
            $log->metadata['changed_fields']
        );
    }

    public function test_anamnesis_notes_are_not_exposed_in_plaintext(): void
    {
        $assessment = $this->assessment();

        $this->putJson($this->url($assessment, 'anamnesis'), [
            'clinical_notes' => 'nota confidencial inicial',
        ])->assertOk();

        $this->putJson($this->url($assessment, 'anamnesis'), [
            'clinical_notes' => 'nota confidencial atualizada',
        ])->assertOk();

        $log = $this->clinicalLog($assessment, 'anamnesis');

        $this->assertContains(
            'clinical_notes',
            $log->metadata['changed_fields']
        );

        $rawMetadata = $log->getRawOriginal('metadata');
        $this->assertStringNotContainsString(
            'nota confidencial atualizada',
            $rawMetadata
        );

        $changes = json_decode(
            Crypt::decryptString($log->metadata['changes_encrypted']),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        $this->assertSame(
            'nota confidencial inicial',
            $changes['clinical_notes']['old']
        );
        $this->assertSame(
            'nota confidencial atualizada',
            $changes['clinical_notes']['new']
        );
    }

    public function test_invalid_update_does_not_log(): void
    {
        $assessment = $this->assessment();

        $this->putJson($this->url($assessment, 'body-composition'), [
            'weight_kg' => -15,
        ])->assertUnprocessable();

        $this->assertSame(0, $this->clinicalCount($assessment));
    }

    private function assessment(): Assessment
    {
        $user = User::factory()->create([
            'role' => UserRole::Evaluator,
            'active' => true,
        ]);

        Sanctum::actingAs($user);

        $student = Student::create([
            'name' => 'Aluno de Teste',
            'birth_date' => '1990-01-01',
            'active' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return Assessment::create([
            'student_id' => $student->id,
            'evaluator_id' => $user->id,
            'evaluation_date' => '2026-10-06',
            'status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    private function url(Assessment $assessment, string $section): string
    {
        return "/api/v1/assessments/{$assessment->uuid}/{$section}";
    }

    private function clinicalLog(Assessment $assessment, string $section): AuditLog
    {
        return AuditLog::query()
            ->where('assessment_id', $assessment->id)
            ->where('action', 'assessment.clinical.update')
            ->get()
            ->last(
                fn (AuditLog $log): bool =>
                    ($log->metadata['section'] ?? null) === $section
            ) ?? throw new \RuntimeException("Log da secao {$section} ausente");
    }

    private function clinicalCount(Assessment $assessment): int
    {
        return AuditLog::query()
            ->where('assessment_id', $assessment->id)
            ->where('action', 'assessment.clinical.update')
            ->count();
    }
}
