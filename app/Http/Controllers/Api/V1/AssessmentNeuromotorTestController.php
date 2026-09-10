<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AssessmentSectionStatus;
use App\Enums\AssessmentSectionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateAssessmentNeuromotorTestRequest;
use App\Models\Assessment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AssessmentNeuromotorTestController extends Controller
{
    public function show(
        Assessment $assessment
    ): JsonResponse {
        $assessment->load([
            'neuromotorTests',
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
        UpdateAssessmentNeuromotorTestRequest $request,
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
                $current =
                    $assessment
                        ->neuromotorTests
                        ?->payload
                    ?? [];

                /*
                 * Permite salvar aos poucos.
                 */
                $payload =
                    array_replace_recursive(
                        $current,
                        $data
                    );

                $results =
                    $this->pendingResults();

                $assessment
                    ->neuromotorTests()
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
                            AssessmentSectionType::NeuromotorTests
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
            'neuromotorTests',
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
        /*
         * Critérios de pontuação,
         * classificação e tabelas ainda
         * aguardam validação profissional.
         */
        return [
            'flexibility' => [
                'classification' =>
                    null,

                'reference_table' =>
                    null,
            ],

            'resistance' => [
                'abdominal_flexion' => [
                    'classification' =>
                        null,

                    'reference_range' =>
                        null,
                ],

                'push_ups' => [
                    'classification' =>
                        null,

                    'reference_range' =>
                        null,
                ],
            ],

            'calculation_status' =>
                'pending_professional_validation',
        ];
    }

    private function responseData(
        Assessment $assessment
    ): array {
        $payload =
            $assessment
                ->neuromotorTests
                ?->payload
            ?? [];

        $results =
            $assessment
                ->neuromotorTests
                ?->results
            ?? $this
                ->pendingResults();

        $section =
            $assessment
                ->sections
                ->first(
                    fn ($item): bool =>
                        $item->section ===
                        AssessmentSectionType::NeuromotorTests
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
                    'neuromotorTests'
                )
                ->with(
                    'neuromotorTests'
                )
                ->orderByDesc(
                    'evaluation_date'
                )
                ->orderByDesc(
                    'id'
                )
                ->first();

        return [
            'assessment_uuid' =>
                $assessment->uuid,

            'neuromotor_tests' => [
                'flexibility' =>
                    $payload[
                        'flexibility'
                    ] ?? [],

                'resistance' =>
                    $payload[
                        'resistance'
                    ] ?? [],
            ],

            'results' =>
                $results,

            'previous' =>
                $previous
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

                        'neuromotor_tests' =>
                            $previous
                                ->neuromotorTests
                                ?->payload
                            ?? [],

                        'results' =>
                            $previous
                                ->neuromotorTests
                                ?->results
                            ?? $this
                                ->pendingResults(),
                    ]
                    : null,

            'configuration' => [
                'flexibility_protocols' => [
                    [
                        'key' =>
                            'wells_dillon',

                        'label' =>
                            'Banco de Wells e Dillon',
                    ],

                    [
                        'key' =>
                            'flexitest',

                        'label' =>
                            'Flexiteste',
                    ],
                ],

                'resistance_tests' => [
                    [
                        'key' =>
                            'abdominal_flexion',

                        'label' =>
                            'Flexão abdominal',
                    ],

                    [
                        'key' =>
                            'push_ups',

                        'label' =>
                            'Flexão de braços',
                    ],
                ],

                'classification_configured' =>
                    false,

                'reference_tables_configured' =>
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
