<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Assessment;
use App\Models\ParqQuestionVersion;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssessmentParqHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_assessment_keeps_question_versions_that_were_answered(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Evaluator,
            'active' => true,
        ]);

        Sanctum::actingAs($user);

        $student = Student::create([
            'name' => 'Aluno Teste',
            'birth_date' => '1990-01-01',
            'active' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $assessment = Assessment::create([
            'student_id' => $student->id,
            'evaluator_id' => $user->id,
            'evaluation_date' => '2026-10-06',
            'status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $versionOne =
            $this->createQuestionSet(
                $user,
                1,
                'Pergunta antiga'
            );

        $answers = [];

        foreach (
            $versionOne as $index => $question
        ) {
            $answers[] = [
                'question_version_id' =>
                    $question->id,

                'answer' =>
                    $index === 0,
            ];
        }

        $this
            ->putJson(
                "/api/v1/assessments/{$assessment->uuid}/anamnesis",
                [
                    'parq_answers' =>
                        $answers,
                ]
            )
            ->assertOk();

        ParqQuestionVersion::query()
            ->where(
                'version',
                1
            )
            ->update([
                'active' => false,
            ]);

        $this->createQuestionSet(
            $user,
            2,
            'Pergunta nova'
        );

        $response =
            $this
                ->getJson(
                    "/api/v1/assessments/{$assessment->uuid}/anamnesis"
                )
                ->assertOk()
                ->assertJsonPath(
                    'data.parq.configured',
                    true
                )
                ->assertJsonPath(
                    'data.parq.answered_count',
                    7
                )
                ->assertJsonPath(
                    'data.parq.total_questions',
                    7
                );

        $questions =
            collect(
                $response->json(
                    'data.parq.questions'
                )
            );

        $this->assertCount(
            7,
            $questions
        );

        $this->assertSame(
            1,
            $questions
                ->first()[
                    'version'
                ]
        );

        $this->assertSame(
            'Pergunta antiga 1',
            $questions
                ->first()[
                    'text'
                ]
        );

        $this->assertTrue(
            $questions
                ->first()[
                    'answer'
                ]
        );

        /*
         * Depois de uma versão nova entrar
         * em vigor, as respostas antigas
         * continuam editáveis usando os IDs
         * originalmente associados.
         */
        $this
            ->putJson(
                "/api/v1/assessments/{$assessment->uuid}/anamnesis",
                [
                    'parq_answers' =>
                        $answers,
                ]
            )
            ->assertOk();
    }

    /**
     * @return array<int, ParqQuestionVersion>
     */
    private function createQuestionSet(
        User $user,
        int $version,
        string $prefix
    ): array {
        $questions = [];

        for (
            $position = 1;
            $position <= 7;
            $position++
        ) {
            $questions[] =
                ParqQuestionVersion::create([
                    'question_key' =>
                        "question_{$position}",

                    'version' =>
                        $version,

                    'position' =>
                        $position,

                    'question_text' =>
                        "{$prefix} {$position}",

                    'active' => true,

                    'approved_at' =>
                        now(),

                    'approved_by' =>
                        $user->id,
                ]);
        }

        return $questions;
    }
}
