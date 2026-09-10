<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AssessmentSectionStatus;
use App\Enums\AssessmentSectionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateAssessmentVo2MaxRequest;
use App\Models\Assessment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AssessmentVo2MaxController extends Controller
{
    public function show(
        Assessment $assessment
    ): JsonResponse {
        $assessment->load([
            'student',
            'vo2Max',
            'sections',
        ]);

        return response()->json([
            'data' =>
                $this->responseData(
                    $assessment
                ),
        ]);
    }

    public function update(
        UpdateAssessmentVo2MaxRequest $request,
        Assessment $assessment
    ): JsonResponse {
        if ($assessment->isCompleted()) {
            abort(
                422,
                'Avaliações concluídas não podem ser alteradas.'
            );
        }

        $assessment->load([
            'student',
            'vo2Max',
        ]);

        $validated =
            $request->validated();

        DB::transaction(
            function () use (
                $assessment,
                $validated,
                $request
            ): void {
                $current =
                    $assessment
                        ->vo2Max
                        ?->payload
                    ?? [];

                $payload =
                    array_replace_recursive(
                        $current,
                        $validated
                    );

                $payload['test'] =
                    $payload['test']
                    ?? [];

                $payload[
                    'test'
                ][
                    'age'
                ] =
                    $assessment
                        ->ageAtEvaluation();

                $payload[
                    'test'
                ][
                    'sex'
                ] =
                    $assessment
                        ->student
                        ?->sex;

                $assessment
                    ->vo2Max()
                    ->updateOrCreate(
                        [],
                        [
                            'payload' =>
                                $payload,

                            'results' =>
                                $this
                                    ->pendingResults(),

                            'updated_by' =>
                                $request
                                    ->user()
                                    ->id,
                        ]
                    );

                $section =
                    $assessment
                        ->sections()
                        ->where(
                            'section',
                            AssessmentSectionType::Vo2Max
                                ->value
                        )
                        ->firstOrFail();

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
                    'updated_by' =>
                        $request
                            ->user()
                            ->id,
                ]);
            }
        );

        $assessment->refresh();

        $assessment->load([
            'student',
            'vo2Max',
            'sections',
        ]);

        return response()->json([
            'data' =>
                $this->responseData(
                    $assessment
                ),
        ]);
    }

    private function pendingResults(): array
    {
        return [
            /*
             * Não calcular até as
             * fórmulas serem validadas.
             */
            'estimated_max_heart_rate' =>
                null,

            'vo2_max_ml_kg_min' =>
                null,

            'classification' =>
                null,

            'reference_range' =>
                null,

            'calculation_status' =>
                'pending_professional_validation',
        ];
    }

    private function responseData(
        Assessment $assessment
    ): array {
        $payload =
            $assessment
                ->vo2Max
                ?->payload
            ?? [];

        $results =
            $assessment
                ->vo2Max
                ?->results
            ?? $this
                ->pendingResults();

        $section =
            $assessment
                ->sections
                ->first(
                    fn ($item): bool =>
                        $item->section ===
                        AssessmentSectionType::Vo2Max
                );

        $previous =
            Assessment::query()
                ->where(
                    'student_id',
                    $assessment
                        ->student_id
                )
                ->whereDate(
                    'evaluation_date',
                    '<',
                    $assessment
                        ->evaluation_date
                        ->format('Y-m-d')
                )
                ->whereHas(
                    'vo2Max'
                )
                ->with(
                    'vo2Max'
                )
                ->orderByDesc(
                    'evaluation_date'
                )
                ->orderByDesc('id')
                ->first();

        $previousPayload =
            $previous
                ?->vo2Max
                ?->payload;

        return [
            'assessment_uuid' =>
                $assessment->uuid,

            'evaluation_date' =>
                $assessment
                    ->evaluation_date
                    ->format('Y-m-d'),

            'student' => [
                'age_at_evaluation' =>
                    $assessment
                        ->ageAtEvaluation(),

                'sex' =>
                    $assessment
                        ->student
                        ?->sex,
            ],

            'vo2_max' => [
                'activity_level' =>
                    $payload[
                        'activity_level'
                    ] ?? null,

                'protocol' =>
                    $payload[
                        'protocol'
                    ] ?? null,

                'test' =>
                    $payload[
                        'test'
                    ] ?? [],
            ],

            'results' =>
                $results,

            'previous' =>
                $previous &&
                $previousPayload
                    ? [
                        'assessment_uuid' =>
                            $previous
                                ->uuid,

                        'evaluation_date' =>
                            $previous
                                ->evaluation_date
                                ->format(
                                    'Y-m-d'
                                ),

                        'activity_level' =>
                            $previousPayload[
                                'activity_level'
                            ] ?? null,

                        'protocol' =>
                            $previousPayload[
                                'protocol'
                            ] ?? null,

                        'test' =>
                            $previousPayload[
                                'test'
                            ] ?? [],

                        'results' =>
                            $previous
                                ->vo2Max
                                ?->results
                            ?? $this
                                ->pendingResults(),
                    ]
                    : null,

            'protocols' => [
                [
                    'key' =>
                        'cooper_12_min',

                    'label' =>
                        'Cooper de 12 minutos',
                ],

                [
                    'key' =>
                        'run_2400m',

                    'label' =>
                        'Corrida de 2.400 metros',
                ],

                [
                    'key' =>
                        'walk_1_mile',

                    'label' =>
                        'Caminhada de 1 milha (~1.600 m)',
                ],
            ],

            'configuration' => [
                'max_heart_rate_formula_configured' =>
                    false,

                'vo2_calculation_configured' =>
                    false,

                'classification_configured' =>
                    false,

                'calculation_status' =>
                    'pending_professional_validation',
            ],

            'section' => [
                'status' =>
                    $section
                        ?->status
                        ->value,

                'status_label' =>
                    $section
                        ?->status
                        ->label(),

                'started_at' =>
                    $section
                        ?->started_at
                        ?->toIso8601String(),

                'completed_at' =>
                    $section
                        ?->completed_at
                        ?->toIso8601String(),
            ],
        ];
    }
}
