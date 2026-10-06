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

class AssessmentSectionWriteLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_form_sections_reject_updates(): void
    {
        $user = $this->createEvaluator();

        Sanctum::actingAs($user);

        $assessment =
            $this->createAssessment($user);

        $cases = [
            [
                AssessmentSectionType::Anamnesis,
                "/api/v1/assessments/{$assessment->uuid}/anamnesis",
                [
                    'clinical_notes' =>
                        'Não deve ser salvo.',
                ],
            ],

            [
                AssessmentSectionType::BodyComposition,
                "/api/v1/assessments/{$assessment->uuid}/body-composition",
                [
                    'weight_kg' => 80,
                ],
            ],

            [
                AssessmentSectionType::Circumferences,
                "/api/v1/assessments/{$assessment->uuid}/anthropometry",
                [
                    'circumferences' => [
                        'waist' => 80,
                    ],
                ],
            ],

            [
                AssessmentSectionType::Vo2Max,
                "/api/v1/assessments/{$assessment->uuid}/vo2-max",
                [
                    'activity_level' =>
                        'active',
                ],
            ],

            [
                AssessmentSectionType::NeuromotorTests,
                "/api/v1/assessments/{$assessment->uuid}/neuromotor-tests",
                [
                    'flexibility' => [
                        'result' => 20,
                    ],
                ],
            ],

            [
                AssessmentSectionType::PosturalAssessment,
                "/api/v1/assessments/{$assessment->uuid}/postural-assessment",
                [
                    'observations' =>
                        'Não deve ser salvo.',
                ],
            ],
        ];

        foreach (
            $cases as [
                $section,
                $url,
                $payload,
            ]
        ) {
            $this->completeSection(
                $assessment,
                $section,
                $user
            );

            $this
                ->putJson(
                    $url,
                    $payload
                )
                ->assertStatus(422)
                ->assertJsonPath(
                    'message',
                    $this->lockedMessage()
                );
        }
    }

    public function test_completed_progress_photos_section_rejects_photo_writes(): void
    {
        $user = $this->createEvaluator();

        Sanctum::actingAs($user);

        $assessment =
            $this->createAssessment($user);

        $assessment
            ->progressPhotos()
            ->create([
                'position' => 'front',
                'photo_path' =>
                    'tests/front.jpg',
                'uploaded_at' => now(),
                'uploaded_by' => $user->id,
                'updated_by' => $user->id,
            ]);

        $this->completeSection(
            $assessment,
            AssessmentSectionType::ProgressPhotos,
            $user
        );

        $baseUrl =
            "/api/v1/assessments/{$assessment->uuid}/progress-photos/front";

        $this
            ->postJson(
                $baseUrl,
                [
                    'photo_base64' => 'abc',
                ]
            )
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                $this->lockedMessage()
            );

        $this
            ->patchJson(
                $baseUrl,
                [
                    'observation' =>
                        'Não deve ser salvo.',
                ]
            )
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                $this->lockedMessage()
            );

        $this
            ->deleteJson($baseUrl)
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                $this->lockedMessage()
            );
    }

    public function test_completed_postural_section_rejects_photo_writes(): void
    {
        $user = $this->createEvaluator();

        Sanctum::actingAs($user);

        $assessment =
            $this->createAssessment($user);

        $assessment
            ->posturalPhotos()
            ->create([
                'position' =>
                    'right_lateral',
                'photo_path' =>
                    'tests/right-lateral.jpg',
                'grid_enabled' => false,
                'uploaded_at' => now(),
                'uploaded_by' => $user->id,
                'updated_by' => $user->id,
            ]);

        $this->completeSection(
            $assessment,
            AssessmentSectionType::PosturalAssessment,
            $user
        );

        $baseUrl =
            "/api/v1/assessments/{$assessment->uuid}/postural-assessment/photos/right_lateral";

        $this
            ->postJson(
                $baseUrl,
                [
                    'photo_base64' => 'abc',
                ]
            )
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                $this->lockedMessage()
            );

        $this
            ->patchJson(
                $baseUrl,
                [
                    'grid_enabled' => true,
                ]
            )
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                $this->lockedMessage()
            );

        $this
            ->deleteJson($baseUrl)
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                $this->lockedMessage()
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

    private function completeSection(
        Assessment $assessment,
        AssessmentSectionType $section,
        User $user
    ): void {
        $assessment
            ->sections()
            ->where(
                'section',
                $section->value
            )
            ->firstOrFail()
            ->changeStatus(
                AssessmentSectionStatus::Completed,
                $user->id
            );
    }

    private function lockedMessage(): string
    {
        return 'Seção concluída. Reabra a seção antes de alterá-la.';
    }
}
