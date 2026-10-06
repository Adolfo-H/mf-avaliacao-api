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

class AssessmentCompletionIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_assessment_cannot_be_completed_when_a_required_section_is_missing(): void
    {
        $user = $this->createEvaluator();

        Sanctum::actingAs($user);

        $assessment =
            $this->createAssessment($user);

        $assessment
            ->sections()
            ->where(
                'section',
                AssessmentSectionType::PosturalAssessment->value
            )
            ->delete();

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

        $this
            ->postJson(
                "/api/v1/assessments/{$assessment->uuid}/complete"
            )
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'A avaliação está inconsistente: todas as seções obrigatórias precisam existir antes da conclusão.'
            )
            ->assertJsonPath(
                'missing_sections.0',
                AssessmentSectionType::PosturalAssessment->value
            )
            ->assertJsonPath(
                'expected_section_count',
                7
            )
            ->assertJsonPath(
                'actual_section_count',
                6
            );

        $this->assertDatabaseHas(
            'assessments',
            [
                'id' => $assessment->id,
                'status' => 'draft',
                'completed_at' => null,
            ]
        );
    }

    public function test_assessment_with_all_seven_completed_sections_can_be_completed(): void
    {
        $user = $this->createEvaluator();

        Sanctum::actingAs($user);

        $assessment =
            $this->createAssessment($user);

        $this->assertCount(
            7,
            $assessment
                ->sections()
                ->get()
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

        $this
            ->postJson(
                "/api/v1/assessments/{$assessment->uuid}/complete"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'completed'
            );

        $this->assertDatabaseHas(
            'assessments',
            [
                'id' => $assessment->id,
                'status' => 'completed',
            ]
        );

        $assessment->refresh();

        $this->assertNotNull(
            $assessment->completed_at
        );
    }

    private function createEvaluator(): User
    {
        return User::factory()->create([
            'role' =>
                UserRole::Evaluator,
            'active' => true,
        ]);
    }

    private function createAssessment(
        User $user
    ): Assessment {
        $student =
            Student::create([
                'name' => 'Aluno Teste',
                'birth_date' =>
                    '1990-01-01',
                'active' => true,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

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
}
