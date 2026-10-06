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

class AssessmentIdentityLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_and_date_can_be_changed_before_assessment_starts(): void
    {
        $user = $this->createEvaluator();

        Sanctum::actingAs($user);

        $firstStudent =
            $this->createStudent(
                $user,
                'Aluno Um'
            );

        $secondStudent =
            $this->createStudent(
                $user,
                'Aluno Dois'
            );

        $assessment =
            $this->createAssessment(
                $user,
                $firstStudent
            );

        $this
            ->putJson(
                "/api/v1/assessments/{$assessment->uuid}",
                [
                    'student_uuid' =>
                        $secondStudent->uuid,

                    'evaluation_date' =>
                        '2026-10-05',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.student.uuid',
                $secondStudent->uuid
            )
            ->assertJsonPath(
                'data.evaluation_date',
                '2026-10-05'
            );

        $this->assertDatabaseHas(
            'assessments',
            [
                'id' => $assessment->id,
                'student_id' =>
                    $secondStudent->id,
                'evaluation_date' =>
                    '2026-10-05',
            ]
        );
    }

    public function test_student_cannot_be_changed_after_a_section_has_started(): void
    {
        $user = $this->createEvaluator();

        Sanctum::actingAs($user);

        $firstStudent =
            $this->createStudent(
                $user,
                'Aluno Um'
            );

        $secondStudent =
            $this->createStudent(
                $user,
                'Aluno Dois'
            );

        $assessment =
            $this->createAssessment(
                $user,
                $firstStudent
            );

        $assessment
            ->sections()
            ->where(
                'section',
                AssessmentSectionType::Anamnesis
                    ->value
            )
            ->firstOrFail()
            ->changeStatus(
                AssessmentSectionStatus::InProgress,
                $user->id
            );

        $this
            ->putJson(
                "/api/v1/assessments/{$assessment->uuid}",
                [
                    'student_uuid' =>
                        $secondStudent->uuid,
                ]
            )
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                $this->lockedMessage()
            );

        $this->assertDatabaseHas(
            'assessments',
            [
                'id' => $assessment->id,
                'student_id' =>
                    $firstStudent->id,
            ]
        );
    }

    public function test_date_cannot_be_changed_when_clinical_data_already_exists(): void
    {
        $user = $this->createEvaluator();

        Sanctum::actingAs($user);

        $student =
            $this->createStudent(
                $user,
                'Aluno Teste'
            );

        $assessment =
            $this->createAssessment(
                $user,
                $student
            );

        /*
         * Simula dado já associado à avaliação
         * mesmo se, por alguma inconsistência,
         * a seção ainda estivesse not_started.
         */
        $assessment
            ->photoConsent()
            ->create([
                'purpose' =>
                    'Registro da evolução física',

                'consent_date' =>
                    '2026-10-06',

                'external_use_allowed' =>
                    false,

                'storage_authorized' =>
                    true,

                'registered_by' =>
                    $user->id,

                'updated_by' =>
                    $user->id,
            ]);

        $this
            ->putJson(
                "/api/v1/assessments/{$assessment->uuid}",
                [
                    'evaluation_date' =>
                        '2026-10-05',
                ]
            )
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                $this->lockedMessage()
            );

        $this->assertDatabaseHas(
            'assessments',
            [
                'id' => $assessment->id,
                'evaluation_date' =>
                    '2026-10-06',
            ]
        );
    }

    public function test_sending_same_student_and_date_is_allowed_after_start(): void
    {
        $user = $this->createEvaluator();

        Sanctum::actingAs($user);

        $student =
            $this->createStudent(
                $user,
                'Aluno Teste'
            );

        $assessment =
            $this->createAssessment(
                $user,
                $student
            );

        $assessment
            ->sections()
            ->where(
                'section',
                AssessmentSectionType::BodyComposition
                    ->value
            )
            ->firstOrFail()
            ->changeStatus(
                AssessmentSectionStatus::InProgress,
                $user->id
            );

        $this
            ->putJson(
                "/api/v1/assessments/{$assessment->uuid}",
                [
                    'student_uuid' =>
                        $student->uuid,

                    'evaluation_date' =>
                        '2026-10-06',
                ]
            )
            ->assertOk();
    }

    private function createEvaluator(): User
    {
        return User::factory()->create([
            'role' =>
                UserRole::Evaluator,
            'active' => true,
        ]);
    }

    private function createStudent(
        User $user,
        string $name
    ): Student {
        return Student::create([
            'name' => $name,
            'birth_date' =>
                '1990-01-01',
            'active' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    private function createAssessment(
        User $user,
        Student $student
    ): Assessment {
        return Assessment::create([
            'student_id' => $student->id,
            'evaluator_id' => $user->id,
            'evaluation_date' =>
                '2026-10-06',
            'status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    private function lockedMessage(): string
    {
        return 'Aluno e data da avaliação não podem ser alterados após o início do preenchimento.';
    }
}
