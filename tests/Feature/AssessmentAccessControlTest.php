<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Assessment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssessmentAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_any_assessment(): void
    {
        $admin =
            $this->createUser(
                UserRole::Admin
            );

        $evaluator =
            $this->createUser(
                UserRole::Evaluator
            );

        $student =
            $this->createStudent($admin);

        $assessment =
            $this->createAssessment(
                $student,
                $evaluator,
                $admin
            );

        Sanctum::actingAs($admin);

        $this
            ->getJson(
                "/api/v1/assessments/{$assessment->uuid}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.uuid',
                $assessment->uuid
            );
    }

    public function test_evaluator_can_access_own_assessment(): void
    {
        $evaluator =
            $this->createUser(
                UserRole::Evaluator
            );

        $student =
            $this->createStudent(
                $evaluator
            );

        $assessment =
            $this->createAssessment(
                $student,
                $evaluator,
                $evaluator
            );

        Sanctum::actingAs(
            $evaluator
        );

        $this
            ->getJson(
                "/api/v1/assessments/{$assessment->uuid}"
            )
            ->assertOk();
    }

    public function test_evaluator_cannot_access_another_evaluators_assessment(): void
    {
        $firstEvaluator =
            $this->createUser(
                UserRole::Evaluator
            );

        $secondEvaluator =
            $this->createUser(
                UserRole::Evaluator
            );

        $student =
            $this->createStudent(
                $secondEvaluator
            );

        $assessment =
            $this->createAssessment(
                $student,
                $secondEvaluator,
                $secondEvaluator
            );

        Sanctum::actingAs(
            $firstEvaluator
        );

        $this
            ->getJson(
                "/api/v1/assessments/{$assessment->uuid}"
            )
            ->assertForbidden();
    }

    public function test_reception_cannot_access_clinical_assessment(): void
    {
        $reception =
            $this->createUser(
                UserRole::Reception
            );

        $evaluator =
            $this->createUser(
                UserRole::Evaluator
            );

        $student =
            $this->createStudent(
                $reception
            );

        $assessment =
            $this->createAssessment(
                $student,
                $evaluator,
                $reception
            );

        Sanctum::actingAs(
            $reception
        );

        $this
            ->getJson(
                "/api/v1/assessments/{$assessment->uuid}"
            )
            ->assertForbidden();

        $this
            ->getJson(
                '/api/v1/assessments'
            )
            ->assertForbidden();
    }

    public function test_evaluator_assessment_list_only_contains_own_assessments(): void
    {
        $firstEvaluator =
            $this->createUser(
                UserRole::Evaluator
            );

        $secondEvaluator =
            $this->createUser(
                UserRole::Evaluator
            );

        $student =
            $this->createStudent(
                $firstEvaluator
            );

        $ownAssessment =
            $this->createAssessment(
                $student,
                $firstEvaluator,
                $firstEvaluator
            );

        $this->createAssessment(
            $student,
            $secondEvaluator,
            $secondEvaluator
        );

        Sanctum::actingAs(
            $firstEvaluator
        );

        $this
            ->getJson(
                '/api/v1/assessments'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.uuid',
                $ownAssessment->uuid
            );
    }

    public function test_evaluator_cannot_create_assessment_for_another_evaluator(): void
    {
        $firstEvaluator =
            $this->createUser(
                UserRole::Evaluator
            );

        $secondEvaluator =
            $this->createUser(
                UserRole::Evaluator
            );

        $student =
            $this->createStudent(
                $firstEvaluator
            );

        Sanctum::actingAs(
            $firstEvaluator
        );

        $this
            ->postJson(
                '/api/v1/assessments',
                [
                    'student_uuid' =>
                        $student->uuid,

                    'evaluator_id' =>
                        $secondEvaluator->id,

                    'evaluation_date' =>
                        '2026-10-06',
                ]
            )
            ->assertForbidden();
    }

    public function test_admin_can_create_assessment_for_evaluator(): void
    {
        $admin =
            $this->createUser(
                UserRole::Admin
            );

        $evaluator =
            $this->createUser(
                UserRole::Evaluator
            );

        $student =
            $this->createStudent(
                $admin
            );

        Sanctum::actingAs($admin);

        $this
            ->postJson(
                '/api/v1/assessments',
                [
                    'student_uuid' =>
                        $student->uuid,

                    'evaluator_id' =>
                        $evaluator->id,

                    'evaluation_date' =>
                        '2026-10-06',
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.evaluator.id',
                $evaluator->id
            );
    }

    public function test_clinical_subroutes_are_also_protected(): void
    {
        $firstEvaluator =
            $this->createUser(
                UserRole::Evaluator
            );

        $secondEvaluator =
            $this->createUser(
                UserRole::Evaluator
            );

        $student =
            $this->createStudent(
                $secondEvaluator
            );

        $assessment =
            $this->createAssessment(
                $student,
                $secondEvaluator,
                $secondEvaluator
            );

        Sanctum::actingAs(
            $firstEvaluator
        );

        $this
            ->getJson(
                "/api/v1/assessments/{$assessment->uuid}/anamnesis"
            )
            ->assertForbidden();

        $this
            ->getJson(
                "/api/v1/assessments/{$assessment->uuid}/postural-assessment/photos/right_lateral"
            )
            ->assertForbidden();
    }

    private function createUser(
        UserRole $role
    ): User {
        return User::factory()->create([
            'role' => $role,
            'active' => true,
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

            'active' => true,

            'created_by' =>
                $user->id,

            'updated_by' =>
                $user->id,
        ]);
    }

    private function createAssessment(
        Student $student,
        User $evaluator,
        User $creator
    ): Assessment {
        return Assessment::create([
            'student_id' =>
                $student->id,

            'evaluator_id' =>
                $evaluator->id,

            'evaluation_date' =>
                '2026-10-06',

            'status' => 'draft',

            'created_by' =>
                $creator->id,

            'updated_by' =>
                $creator->id,
        ]);
    }
}
