<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AssessmentSectionStatus;
use App\Enums\AssessmentSectionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateAssessmentBodyCompositionRequest;
use App\Models\Assessment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AssessmentBodyCompositionController extends Controller
{
    public function show(
        Assessment $assessment
    ): JsonResponse {
        $assessment->load([
            'bodyComposition',
            'sections',
        ]);

        return response()->json([
            'data' => $this->buildResponse(
                $assessment
            ),
        ]);
    }

    public function update(
        UpdateAssessmentBodyCompositionRequest $request,
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

        DB::transaction(
            function () use (
                $assessment,
                $request,
                $data
            ): void {
                $currentPayload =
                    $assessment
                        ->bodyComposition
                        ?->payload
                    ?? [];

                /*
                 * Permite salvar parcialmente.
                 */
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
                    ->bodyComposition()
                    ->updateOrCreate(
                        [],
                        [
                            'payload' => $payload,

                            'results' => $results,

                            'updated_by' => $request
                                ->user()
                                ->id,
                        ]
                    );

                $section =
                    $assessment
                        ->sections()
                        ->where(
                            'section',
                            AssessmentSectionType::BodyComposition
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
                    'updated_by' => $request
                        ->user()
                        ->id,
                ]);
            }
        );

        $assessment->refresh();

        $assessment->load([
            'bodyComposition',
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
        $weight =
            isset(
                $payload['weight_kg']
            )
                ? (float) $payload[
                    'weight_kg'
                ]
                : null;

        $height =
            isset(
                $payload['height_m']
            )
                ? (float) $payload[
                    'height_m'
                ]
                : null;

        $bmi = null;

        if (
            $weight !== null &&
            $weight > 0 &&
            $height !== null &&
            $height > 0
        ) {
            $bmi =
                round(
                    $weight /
                        (
                            $height *
                            $height
                        ),
                    2
                );
        }

        return [
            /*
             * Fórmula definida no requisito:
             *
             * IMC = peso / altura²
             */
            'bmi' => $bmi !== null
                    ? [
                        'value' => $bmi,

                        'formula' => 'weight_kg / (height_m ^ 2)',

                        'version' => '1.0',

                        'calculated_at' => now()
                            ->toIso8601String(),
                    ]
                    : null,

            /*
             * Estes cálculos permanecem
             * bloqueados até validação
             * profissional.
             */
            'bmi_classification' => null,

            'body_fat_percentage' => null,

            'fat_mass_kg' => null,

            'lean_mass_kg' => null,
        ];
    }

    private function buildResponse(
        Assessment $assessment
    ): array {
        $payload =
            $assessment
                ->bodyComposition
                ?->payload
            ?? [];

        $results =
            $assessment
                ->bodyComposition
                ?->results
            ?? [];

        $section =
            $assessment
                ->sections
                ->first(
                    fn ($item): bool => $item->section ===
                        AssessmentSectionType::BodyComposition
                );

        /*
         * Última avaliação anterior
         * à data desta avaliação.
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
                    'bodyComposition'
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
                ?->bodyComposition
                ?->payload;

        return [
            'assessment_uuid' => $assessment->uuid,

            'body_composition' => $payload,

            'results' => $results,

            'previous' => $previousAssessment &&
                $previousPayload
                    ? [
                        'assessment_uuid' => $previousAssessment
                            ->uuid,

                        'evaluation_date' => $previousAssessment
                            ->evaluation_date
                            ->format(
                                'Y-m-d'
                            ),

                        'weight_kg' => $previousPayload[
                                'weight_kg'
                            ] ?? null,

                        'height_m' => $previousPayload[
                                'height_m'
                            ] ?? null,

                        'target_body_fat_percentage' => $previousPayload[
                                'target_body_fat_percentage'
                            ] ?? null,

                        'skinfolds' => $previousPayload[
                                'skinfolds'
                            ] ?? [],
                    ]
                    : null,

            /*
             * Protocolos previstos pela
             * especificação.
             */
            'protocols' => [
                [
                    'key' => 'pollock_7',

                    'label' => 'Pollock de 7 dobras',

                    'fields_configured' => true,

                    'calculation_configured' => false,
                ],

                [
                    'key' => 'pollock_3',

                    'label' => 'Pollock de 3 dobras',

                    'fields_configured' => false,

                    'calculation_configured' => false,
                ],

                [
                    'key' => 'guedes_3',

                    'label' => 'Guedes de 3 dobras',

                    'fields_configured' => false,

                    'calculation_configured' => false,
                ],

                [
                    'key' => 'bioimpedance',

                    'label' => 'Bioimpedância',

                    'fields_configured' => false,

                    'calculation_configured' => false,
                ],

                [
                    'key' => 'weltman',

                    'label' => 'Weltman para obesos',

                    'fields_configured' => false,

                    'calculation_configured' => false,
                ],
            ],

            'configuration' => [
                'bmi_calculation_configured' => true,

                'bmi_classification_configured' => false,

                'body_fat_calculation_configured' => false,
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
