<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AssessmentSectionStatus;
use App\Enums\AssessmentSectionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateAssessmentAnamnesisRequest;
use App\Models\Assessment;
use App\Models\ParqQuestionVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssessmentAnamnesisController extends Controller
{
    public function show(
        Assessment $assessment
    ): JsonResponse {
        $assessment->load([
            'anamnesis',
            'parqAnswers',
            'sections',
        ]);

        return response()->json([
            'data' => $this->buildResponse(
                $assessment
            ),
        ]);
    }

    public function update(
        UpdateAssessmentAnamnesisRequest $request,
        Assessment $assessment
    ): JsonResponse {
        if ($assessment->isCompleted()) {
            abort(
                422,
                'Avaliações concluídas não podem ser alteradas.'
            );
        }

        $data =
            $request->validated();

        $parqAnswers =
            $data['parq_answers']
            ?? null;

        unset(
            $data['parq_answers']
        );

        /*
         * Cirurgia não pode ter ocorrido
         * depois da data da avaliação.
         */
        if (
            ! empty(
                $data['surgery_date']
            ) &&
            $data['surgery_date'] >
                $assessment
                    ->evaluation_date
                    ->format('Y-m-d')
        ) {
            throw ValidationException::withMessages([
                'surgery_date' => 'A data da cirurgia não pode ser posterior à data da avaliação.',
            ]);
        }

        DB::transaction(
            function () use (
                $assessment,
                $request,
                $data,
                $parqAnswers
            ): void {
                $currentPayload =
                    $assessment
                        ->anamnesis
                        ?->payload
                    ?? [];

                /*
                 * O salvamento é parcial.
                 * Campos não enviados permanecem.
                 * Campos enviados como null são limpos.
                 */
                $payload =
                    array_replace(
                        $currentPayload,
                        $data
                    );

                $assessment
                    ->anamnesis()
                    ->updateOrCreate(
                        [],
                        [
                            'payload' => $payload,

                            'updated_by' => $request
                                ->user()
                                ->id,
                        ]
                    );

                if (
                    $parqAnswers !== null
                ) {
                    $approvedQuestions =
                        ParqQuestionVersion::query()
                            ->where(
                                'active',
                                true
                            )
                            ->whereNotNull(
                                'approved_at'
                            )
                            ->get()
                            ->keyBy('id');

                    /*
                     * Não aceitamos respostas
                     * enquanto não houver uma
                     * versão oficial aprovada
                     * com exatamente 7 perguntas.
                     */
                    if (
                        $approvedQuestions
                            ->count() !== 7
                    ) {
                        throw ValidationException::withMessages([
                            'parq_answers' => 'A versão oficial do PAR-Q ainda não foi aprovada ou configurada.',
                        ]);
                    }

                    foreach (
                        $parqAnswers as $answer
                    ) {
                        $questionId =
                            (int) $answer[
                                'question_version_id'
                            ];

                        if (
                            ! $approvedQuestions
                                ->has(
                                    $questionId
                                )
                        ) {
                            throw ValidationException::withMessages([
                                'parq_answers' => 'Uma das perguntas do PAR-Q não pertence à versão ativa aprovada.',
                            ]);
                        }

                        $assessment
                            ->parqAnswers()
                            ->updateOrCreate(
                                [
                                    'question_version_id' => $questionId,
                                ],
                                [
                                    'answer' => (bool) $answer[
                                            'answer'
                                        ],

                                    'answered_by' => $request
                                        ->user()
                                        ->id,
                                ]
                            );
                    }
                }

                $section =
                    $assessment
                        ->sections()
                        ->where(
                            'section',
                            AssessmentSectionType::Anamnesis
                                ->value
                        )
                        ->firstOrFail();

                /*
                 * Qualquer salvamento real
                 * inicia a seção.
                 */
                if (
                    $section->status ===
                    AssessmentSectionStatus::NotStarted
                ) {
                    $section->changeStatus(
                        AssessmentSectionStatus::InProgress,
                        $request
                            ->user()
                            ->id
                    );
                }

                $assessment->update([
                    'updated_by' => $request
                        ->user()
                        ->id,
                ]);
            }
        );

        $assessment->refresh();

        $assessment->load([
            'anamnesis',
            'parqAnswers',
            'sections',
        ]);

        return response()->json([
            'data' => $this->buildResponse(
                $assessment
            ),
        ]);
    }

    private function buildResponse(
        Assessment $assessment
    ): array {
        $questions =
            ParqQuestionVersion::query()
                ->where(
                    'active',
                    true
                )
                ->whereNotNull(
                    'approved_at'
                )
                ->orderBy(
                    'position'
                )
                ->get();

        $answers =
            $assessment
                ->parqAnswers
                ->keyBy(
                    'question_version_id'
                );

        $positiveAnswers =
            $answers
                ->filter(
                    fn ($answer): bool => $answer->answer === true
                )
                ->count();

        $section =
            $assessment
                ->sections
                ->first(
                    fn ($item): bool => $item->section ===
                        AssessmentSectionType::Anamnesis
                );

        return [
            'assessment_uuid' => $assessment->uuid,

            'anamnesis' => $assessment
                ->anamnesis
                ?->payload
                ?? [],

            'parq' => [
                /*
                 * Só consideramos configurado
                 * quando houver as sete perguntas
                 * aprovadas.
                 */
                'configured' => $questions->count() === 7,

                'questions' => $questions->map(
                    function (
                        $question
                    ) use (
                        $answers
                    ): array {
                        $answer =
                            $answers->get(
                                $question->id
                            );

                        return [
                            'id' => $question->id,

                            'uuid' => $question->uuid,

                            'key' => $question
                                ->question_key,

                            'version' => $question
                                ->version,

                            'position' => $question
                                ->position,

                            'text' => $question
                                ->question_text,

                            'answer' => $answer
                                    ? $answer
                                        ->answer
                                    : null,
                        ];
                    }
                )
                    ->values(),

                'answered_count' => $answers->count(),

                'total_questions' => $questions->count(),

                'has_positive_answer' => $positiveAnswers > 0,

                /*
                 * A interface usará isso
                 * para mostrar o alerta.
                 */
                'medical_alert' => $positiveAnswers > 0,
            ],

            'section' => [
                'status' => $section
                ?->status
                ->value,

                'status_label' => $section
                ?->status
                ->label(),

                'started_at' => $section
                ?->started_at
                ?->toIso8601String(),

                'completed_at' => $section
                ?->completed_at
                ?->toIso8601String(),
            ],
        ];
    }
}
