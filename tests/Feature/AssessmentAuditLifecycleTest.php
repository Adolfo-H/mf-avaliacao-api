<?php

namespace Tests\Feature;

use App\Enums\AssessmentSectionStatus;
use App\Enums\AssessmentSectionType;
use App\Enums\UserRole;
use App\Models\Assessment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssessmentAuditLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_assessment_creation_is_audited(): void
    {
        $user =
            $this->createEvaluator();

        $student =
            $this->createStudent(
                $user
            );

        Sanctum::actingAs($user);

        $response =
            $this
                ->postJson(
                    '/api/v1/assessments',
                    [
                        'student_uuid' =>
                            $student->uuid,

                        'evaluator_id' =>
                            $user->id,

                        'evaluation_date' =>
                            '2026-10-06',
                    ]
                )
                ->assertCreated();

        $assessment =
            Assessment::query()
                ->where(
                    'uuid',
                    $response->json(
                        'data.uuid'
                    )
                )
                ->firstOrFail();

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'user_id' =>
                    $user->id,

                'action' =>
                    'assessment.create',

                'auditable_type' =>
                    Assessment::class,

                'auditable_id' =>
                    $assessment->id,

                'assessment_id' =>
                    $assessment->id,

                'student_id' =>
                    $student->id,
            ]
        );
    }

    public function test_assessment_update_is_audited(): void
    {
        $user =
            $this->createEvaluator();

        $student =
            $this->createStudent(
                $user
            );

        $assessment =
            $this->createAssessment(
                $student,
                $user
            );

        Sanctum::actingAs($user);

        $this
            ->putJson(
                "/api/v1/assessments/{$assessment->uuid}",
                [
                    'evaluation_date' =>
                        '2026-10-05',
                ]
            )
            ->assertOk();

        $log =
            $this->assertAuditExists(
                'assessment.update',
                $assessment
            );

        $metadata =
            $log->metadata;

        $this->assertSame(
            '2026-10-06',
            $metadata[
                'old_values'
            ][
                'evaluation_date'
            ]
        );

        $this->assertSame(
            '2026-10-05',
            $metadata[
                'new_values'
            ][
                'evaluation_date'
            ]
        );
    }

    public function test_section_complete_and_reopen_are_audited(): void
    {
        $user =
            $this->createEvaluator();

        $student =
            $this->createStudent(
                $user
            );

        $assessment =
            $this->createAssessment(
                $student,
                $user
            );

        $section =
            $assessment
                ->sections()
                ->where(
                    'section',
                    AssessmentSectionType::Anamnesis
                        ->value
                )
                ->firstOrFail();

        $section->changeStatus(
            AssessmentSectionStatus::InProgress,
            $user->id
        );

        Sanctum::actingAs($user);

        $sectionValue =
            AssessmentSectionType::Anamnesis
                ->value;

        $this
            ->postJson(
                "/api/v1/assessments/{$assessment->uuid}/sections/{$sectionValue}/complete"
            )
            ->assertOk();

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'user_id' =>
                    $user->id,

                'action' =>
                    'assessment.section.complete',

                'assessment_id' =>
                    $assessment->id,

                'student_id' =>
                    $student->id,
            ]
        );

        $this
            ->postJson(
                "/api/v1/assessments/{$assessment->uuid}/sections/{$sectionValue}/reopen"
            )
            ->assertOk();

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'user_id' =>
                    $user->id,

                'action' =>
                    'assessment.section.reopen',

                'assessment_id' =>
                    $assessment->id,

                'student_id' =>
                    $student->id,
            ]
        );
    }

    public function test_assessment_completion_is_audited(): void
    {
        $user =
            $this->createEvaluator();

        $student =
            $this->createStudent(
                $user
            );

        $assessment =
            $this->createAssessment(
                $student,
                $user
            );

        foreach (
            $assessment
                ->sections()
                ->get() as $section
        ) {
            $section->changeStatus(
                AssessmentSectionStatus::Completed,
                $user->id
            );
        }

        Sanctum::actingAs($user);

        $this
            ->postJson(
                "/api/v1/assessments/{$assessment->uuid}/complete"
            )
            ->assertOk();

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'user_id' =>
                    $user->id,

                'action' =>
                    'assessment.complete',

                'auditable_type' =>
                    Assessment::class,

                'auditable_id' =>
                    $assessment->id,

                'assessment_id' =>
                    $assessment->id,

                'student_id' =>
                    $student->id,
            ]
        );
    }

    private function assertAuditExists(
        string $action,
        Assessment $assessment
    ) {
        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    $action,

                'auditable_type' =>
                    Assessment::class,

                'auditable_id' =>
                    $assessment->id,
            ]
        );

        return
            \App\Models\AuditLog::query()
                ->where(
                    'action',
                    $action
                )
                ->where(
                    'auditable_type',
                    Assessment::class
                )
                ->where(
                    'auditable_id',
                    $assessment->id
                )
                ->latest('id')
                ->firstOrFail();
    }

    private function createEvaluator(): User
    {
        return User::factory()->create([
            'role' =>
                UserRole::Evaluator,

            'active' =>
                true,
        ]);
    }

    private function createStudent(
        User $user
    ): Student {
        return Student::create([
            'name' =>
                'Aluno Teste',

            'birth_date' =>
                '1990-01-01',

            'active' =>
                true,

            'created_by' =>
                $user->id,

            'updated_by' =>
                $user->id,
        ]);
    }

    private function createAssessment(
        Student $student,
        User $evaluator
    ): Assessment {
        return Assessment::create([
            'student_id' =>
                $student->id,

            'evaluator_id' =>
                $evaluator->id,

            'evaluation_date' =>
                '2026-10-06',

            'status' =>
                'draft',

            'created_by' =>
                $evaluator->id,

            'updated_by' =>
                $evaluator->id,
        ]);
    }
}
