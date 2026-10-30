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

/**
 * Etapa 09: regressão INTEGRADA das prévias 08A–08F.
 * Somente dados fictícios criados pelo RefreshDatabase de TESTE.
 * Os GETs não podem alterar resultados oficiais.
 */
final class ClinicalModulesIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const PREVIEWS = [
        'body-composition/clinical-preview' => 'assessment.clinical_preview.view',
        'vo2-max/cooper-preview' => 'assessment.vo2_cooper_preview.view',
        'vo2-max/rockport-preview' => 'assessment.vo2_rockport_preview.view',
        'body-composition/guedes-preview' => 'assessment.guedes_preview.view',
        'body-composition/bioimpedance-preview' => 'assessment.bioimpedance_preview.view',
        'body-composition/weltman-preview' => 'assessment.weltman_preview.view',
    ];

    public function test_all_six_previews_are_private_audited_and_do_not_change_official_results(): void
    {
        $admin = $this->createUser(UserRole::Admin);
        $evaluator = $this->createUser(UserRole::Evaluator);
        $student = $this->createStudent($admin);
        $assessment = $this->createAssessment($student, $evaluator);

        $body = $assessment->bodyComposition()->create([
            'payload' => [
                'protocol' => 'pollock_7',
                'weight_kg' => 81.37,
                'height_m' => 1.78,
                'skinfolds' => [
                    'chest' => 12.5,
                    'midaxillary' => 13.5,
                    'triceps' => 14.5,
                    'subscapular' => 15.5,
                    'abdominal' => 16.5,
                    'suprailiac' => 17.5,
                    'thigh' => 18.5,
                ],
            ],
            'results' => [
                'bmi' => ['value' => 25.67, 'version' => '1.0'],
                'body_fat_percentage' => null,
            ],
            'updated_by' => $evaluator->id,
        ]);
        $vo2 = $assessment->vo2Max()->create([
            'payload' => [
                'protocol' => 'cooper_12_min',
                'test' => ['distance_m' => 2400, 'time_seconds' => 720],
            ],
            'results' => [
                'vo2_max_ml_kg_min' => null,
                'calculation_status' => 'pending_professional_validation',
            ],
            'updated_by' => $evaluator->id,
        ]);

        $bodyBefore = $body->getRawOriginal('results');
        $vo2Before = $vo2->getRawOriginal('results');
        $bodyPayloadBefore = $body->getRawOriginal('payload');
        $vo2PayloadBefore = $vo2->getRawOriginal('payload');
        $bodyUpdatedBefore = (string) $body->getRawOriginal('updated_at');
        $vo2UpdatedBefore = (string) $vo2->getRawOriginal('updated_at');

        Sanctum::actingAs($admin);
        foreach (array_keys(self::PREVIEWS) as $suffix) {
            $response = $this->getJson($this->url($assessment, $suffix))
                ->assertOk()
                ->assertJsonPath('data.assessment_uuid', $assessment->uuid);
            $this->assertStringContainsString(
                'no-store',
                (string) $response->headers->get('Cache-Control'),
                'A prévia precisa ser privada e não armazenada em cache.'
            );
        }

        $bodyAfter = $body->fresh();
        $vo2After = $vo2->fresh();
        $this->assertNotNull($bodyAfter);
        $this->assertNotNull($vo2After);
        $this->assertSame($bodyBefore, $bodyAfter->getRawOriginal('results'));
        $this->assertSame($vo2Before, $vo2After->getRawOriginal('results'));
        $this->assertSame($bodyPayloadBefore, $bodyAfter->getRawOriginal('payload'));
        $this->assertSame($vo2PayloadBefore, $vo2After->getRawOriginal('payload'));
        $this->assertSame($bodyUpdatedBefore, (string) $bodyAfter->getRawOriginal('updated_at'));
        $this->assertSame($vo2UpdatedBefore, (string) $vo2After->getRawOriginal('updated_at'));
        $this->assertNull($bodyAfter->results['body_fat_percentage']);
        $this->assertNull($vo2After->results['vo2_max_ml_kg_min']);

        $logs = AuditLog::query()->whereIn('action', array_values(self::PREVIEWS))->get();
        $this->assertCount(6, $logs, 'Cada visualização autorizada precisa ser auditada.');
        foreach ($logs as $log) {
            $this->assertSame($admin->id, $log->user_id);
            $this->assertSame($assessment->id, $log->assessment_id);
            $this->assertSame($student->id, $log->student_id);
            $metadata = (array) ($log->metadata ?? []);
            foreach (['weight_kg', 'distance_m', 'skinfolds', 'sex', 'age', 'result', 'vo2_max_ml_kg_min'] as $privateKey) {
                $this->assertArrayNotHasKey($privateKey, $metadata);
            }
        }
    }

    public function test_reception_cannot_access_any_clinical_preview(): void
    {
        $reception = $this->createUser(UserRole::Reception);
        $evaluator = $this->createUser(UserRole::Evaluator);
        $student = $this->createStudent($reception);
        $assessment = $this->createAssessment($student, $evaluator);

        Sanctum::actingAs($reception);
        foreach (array_keys(self::PREVIEWS) as $suffix) {
            $this->getJson($this->url($assessment, $suffix))->assertForbidden();
        }
        $this->assertSame(0, AuditLog::query()->whereIn('action', array_values(self::PREVIEWS))->count());
    }

    public function test_other_evaluator_cannot_access_any_clinical_preview(): void
    {
        $owner = $this->createUser(UserRole::Evaluator);
        $other = $this->createUser(UserRole::Evaluator);
        $student = $this->createStudent($owner);
        $assessment = $this->createAssessment($student, $owner);

        Sanctum::actingAs($other);
        foreach (array_keys(self::PREVIEWS) as $suffix) {
            $this->getJson($this->url($assessment, $suffix))->assertForbidden();
        }
        $this->assertSame(0, AuditLog::query()->whereIn('action', array_values(self::PREVIEWS))->count());
    }

    public function test_unauthenticated_access_is_denied_to_all_previews(): void
    {
        $admin = $this->createUser(UserRole::Admin);
        $evaluator = $this->createUser(UserRole::Evaluator);
        $student = $this->createStudent($admin);
        $assessment = $this->createAssessment($student, $evaluator);

        foreach (array_keys(self::PREVIEWS) as $suffix) {
            $this->getJson($this->url($assessment, $suffix))->assertUnauthorized();
        }
    }

    public function test_pollock_three_fields_are_selectable_but_official_formula_remains_disabled(): void
    {
        $admin = $this->createUser(UserRole::Admin);
        $evaluator = $this->createUser(UserRole::Evaluator);
        $student = $this->createStudent($admin);
        $assessment = $this->createAssessment($student, $evaluator);

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/assessments/'.$assessment->uuid.'/body-composition')
            ->assertOk()
            ->assertJsonPath('data.protocols.1.key', 'pollock_3')
            ->assertJsonPath('data.protocols.1.fields_configured', true)
            ->assertJsonPath('data.protocols.1.calculation_configured', false)
            ->assertJsonPath('data.configuration.body_fat_calculation_configured', false);
    }

    private function url(Assessment $assessment, string $suffix): string
    {
        return '/api/v1/assessments/'.$assessment->uuid.'/'.$suffix;
    }

    private function createUser(UserRole $role): User
    {
        return User::factory()->create(['role' => $role, 'active' => true]);
    }

    private function createStudent(User $actor): Student
    {
        return Student::create([
            'name' => 'Aluno Teste Integrado',
            'birth_date' => '1991-04-01',
            'sex' => 'male',
            'active' => true,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    private function createAssessment(Student $student, User $evaluator): Assessment
    {
        return Assessment::create([
            'student_id' => $student->id,
            'evaluator_id' => $evaluator->id,
            'evaluation_date' => '2026-10-10',
            'status' => 'draft',
            'created_by' => $evaluator->id,
            'updated_by' => $evaluator->id,
        ]);
    }
}
