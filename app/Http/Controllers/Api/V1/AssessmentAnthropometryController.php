<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AssessmentSectionStatus;
use App\Enums\AssessmentSectionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateAssessmentAnthropometryRequest;
use App\Models\Assessment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AssessmentAnthropometryController extends Controller
{
    public function show(
        Assessment $assessment
    ): JsonResponse {
        $assessment->load([
            'anthropometry',
            'sections',
        ]);

        return response()->json([
            'data' => $this->buildResponse(
                $assessment
            ),
        ]);
    }

    public function update(
        UpdateAssessmentAnthropometryRequest $request,
        Assessment $assessment
    ): JsonResponse {
        if ($assessment->isCompleted()) {
            abort(
                422,
                'Avaliações concluídas não podem ser alteradas.'
            );
        }

        $data = $request->validated();

        DB::transaction(
            function () use (
                $assessment,
                $request,
                $data
            ): void {
                /*
                 * Permite salvar a seção
                 * aos poucos.
                 */
                $currentPayload =
                    $assessment
                        ->anthropometry
                        ?->payload
                    ?? [];

                $payload =
                    array_replace_recursive(
                        $currentPayload,
                        $data
                    );

                $results =
                    $this->calculateResults(
                        $payload
                    );

                $assessment
                    ->anthropometry()
                    ->updateOrCreate(
                        [],
                        [
                            'payload' =>
                                $payload,

                            'results' =>
                                $results,

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
                            AssessmentSectionType::Circumferences
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
            'anthropometry',
            'sections',
        ]);

        return response()->json([
            'data' => $this->buildResponse(
                $assessment
            ),
        ]);
    }

    private function calculateResults(
        array $payload
    ): array {
        $circumferences =
            $payload['circumferences']
            ?? [];

        $waist =
            isset(
                $circumferences['waist']
            )
                ? (float) $circumferences[
                    'waist'
                ]
                : null;

        $hip =
            isset(
                $circumferences['hip']
            )
                ? (float) $circumferences[
                    'hip'
                ]
                : null;

        $ratio = null;

        if (
            $waist !== null &&
            $waist > 0 &&
            $hip !== null &&
            $hip > 0
        ) {
            /*
             * RCQ = cintura / quadril
             */
            $ratio = round(
                $waist / $hip,
                4
            );
        }

        return [
            'waist_to_hip_ratio' =>
                $ratio !== null
                    ? [
                        'value' =>
                            $ratio,

                        'formula' =>
                            'circumferences.waist / circumferences.hip',

                        'version' =>
                            '1.0',

                        'calculated_at' =>
                            now()
                                ->toIso8601String(),
                    ]
                    : null,

            /*
             * Não implementar classificação
             * sem tabela validada pelo
             * profissional responsável.
             */
            'waist_to_hip_ratio_classification' =>
                null,
        ];
    }

    private function buildResponse(
        Assessment $assessment
    ): array {
        $payload =
            $assessment
                ->anthropometry
                ?->payload
            ?? [];

        $results =
            $assessment
                ->anthropometry
                ?->results
            ?? [];

        $section =
            $assessment
                ->sections
                ->first(
                    fn ($item): bool =>
                        $item->section ===
                        AssessmentSectionType::Circumferences
                );

        /*
         * Busca a avaliação anterior
         * do mesmo aluno.
         */
        $previousAssessment =
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
                        ->format(
                            'Y-m-d'
                        )
                )
                ->with(
                    'anthropometry'
                )
                ->orderByDesc(
                    'evaluation_date'
                )
                ->orderByDesc(
                    'id'
                )
                ->first();

        $previousPayload =
            $previousAssessment
                ?->anthropometry
                ?->payload;

        $previousResults =
            $previousAssessment
                ?->anthropometry
                ?->results;

        return [
            'assessment_uuid' =>
                $assessment->uuid,

            'anthropometry' => [
                'circumferences' =>
                    $payload[
                        'circumferences'
                    ] ?? [],

                'bone_diameters' =>
                    $payload[
                        'bone_diameters'
                    ] ?? [],
            ],

            'results' =>
                $results,

            'previous' =>
                $previousAssessment &&
                $previousPayload
                    ? [
                        'assessment_uuid' =>
                            $previousAssessment
                                ->uuid,

                        'evaluation_date' =>
                            $previousAssessment
                                ->evaluation_date
                                ->format(
                                    'Y-m-d'
                                ),

                        'circumferences' =>
                            $previousPayload[
                                'circumferences'
                            ] ?? [],

                        'bone_diameters' =>
                            $previousPayload[
                                'bone_diameters'
                            ] ?? [],

                        'results' =>
                            $previousResults
                            ?? [],
                    ]
                    : null,

            'configuration' => [
                'waist_to_hip_ratio_calculation_configured' =>
                    true,

                'waist_to_hip_ratio_classification_configured' =>
                    false,

                'circumference_unit' =>
                    'cm',

                'bone_diameter_unit' =>
                    'cm',
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
