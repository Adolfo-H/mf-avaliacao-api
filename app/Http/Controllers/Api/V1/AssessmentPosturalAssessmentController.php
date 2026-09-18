<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AssessmentSectionStatus;
use App\Enums\AssessmentSectionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateAssessmentPosturalAssessmentRequest;
use App\Models\Assessment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AssessmentPosturalAssessmentController extends Controller
{
    private const LATERAL = [
        'hip_anteversion' =>
            'Anteversão de quadril',

        'hip_retroversion' =>
            'Retroversão de quadril',

        'internal_shoulder_rotation' =>
            'Rotação interna dos ombros',

        'cervical_straightening' =>
            'Retificação cervical',

        'lumbar_straightening' =>
            'Retificação lombar',

        'abdominal_protrusion' =>
            'Protusão abdominal',

        'cervical_hyperlordosis' =>
            'Hiperlordose cervical',

        'lumbar_hyperlordosis' =>
            'Hiperlordose lombar',

        'thoracic_hyperkyphosis' =>
            'Hipercifose torácica',

        'flat_foot' =>
            'Pé plano',

        'high_arch_foot' =>
            'Pé cavo',

        'calcaneus_foot' =>
            'Pé calcâneo',

        'equinus_foot' =>
            'Pé equino',

        'flexed_knee' =>
            'Genu flexo',

        'recurvatum_knee' =>
            'Genu recurvado',
    ];

    private const POSTERIOR = [
        'cervical_scoliosis' =>
            'Escoliose cervical',

        'thoracic_scoliosis' =>
            'Escoliose torácica',

        'lumbar_scoliosis' =>
            'Escoliose lombar',

        'scapular_protraction' =>
            'Protração escapular',

        'scapular_retraction' =>
            'Retração escapular',

        'scapular_depression' =>
            'Depressão escapular',

        'valgus_foot' =>
            'Pé valgo',

        'varus_foot' =>
            'Pé varo',

        'trapezius_shortening' =>
            'Encurtamento de trapézio',
    ];

    private const ANTERIOR = [
        'genu_valgum' =>
            'Genu valgo',

        'genu_varum' =>
            'Genu varo',

        'adducted_foot' =>
            'Pé aduto',

        'abducted_foot' =>
            'Pé abduto',
    ];

    private const SHOULDERS = [
        'none' =>
            'Nenhuma elevação',

        'right_elevated' =>
            'Elevação do ombro direito',

        'left_elevated' =>
            'Elevação do ombro esquerdo',
    ];

    private const HIPS = [
        'none' =>
            'Nenhuma elevação',

        'right_elevated' =>
            'Elevação da pelve direita',

        'left_elevated' =>
            'Elevação da pelve esquerda',
    ];

    private const PHOTO_POSITIONS = [
        'right_lateral' =>
            'Lateral direita',

        'left_lateral' =>
            'Lateral esquerda',

        'posterior' =>
            'Posterior',

        'anterior' =>
            'Anterior',
    ];

    public function show(
        Assessment $assessment
    ): JsonResponse {
        $assessment->load([
            'posturalAssessment',
            'posturalPhotos',
            'photoConsent',
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
        UpdateAssessmentPosturalAssessmentRequest $request,
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
                        ->posturalAssessment
                        ?->payload
                    ?? [];

                $payload =
                    array_replace_recursive(
                        $current,
                        $data
                    );

                foreach (
                    [
                        'lateral',
                        'posterior',
                        'anterior',
                    ] as $view
                ) {
                    if (
                        array_key_exists(
                            'alterations',
                            $data[$view] ?? []
                        )
                    ) {
                        $payload[
                            $view
                        ][
                            'alterations'
                        ] =
                            $data[
                                $view
                            ][
                                'alterations'
                            ];
                    }
                }

                $assessment
                    ->posturalAssessment()
                    ->updateOrCreate(
                        [],
                        [
                            'payload' =>
                                $payload,

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
                            AssessmentSectionType::PosturalAssessment
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
            'posturalAssessment',
            'posturalPhotos',
            'photoConsent',
            'sections',
        ]);

        return response()->json([
            'data' =>
                $this->responseData(
                    $assessment
                ),
        ]);
    }

    private function responseData(
        Assessment $assessment
    ): array {
        $payload =
            $assessment
                ->posturalAssessment
                ?->payload
            ?? [];

        $section =
            $assessment
                ->sections
                ->first(
                    fn ($item): bool =>
                        $item->section ===
                        AssessmentSectionType::PosturalAssessment
                );

        $photos =
            $assessment
                ->posturalPhotos
                ->keyBy(
                    'position'
                );

        $photoData = [];

        foreach (
            self::PHOTO_POSITIONS as
            $position => $label
        ) {
            $photo =
                $photos->get(
                    $position
                );

            $photoData[] =
                $photo
                    ? [
                        'position' =>
                            $position,

                        'label' =>
                            $label,

                        'has_photo' =>
                            true,

                        'grid_enabled' =>
                            $photo
                                ->grid_enabled,

                        'grid_settings' =>
                            $photo
                                ->grid_settings,

                        'observation' =>
                            $photo
                                ->observation,

                        'captured_at' =>
                            $photo
                                ->captured_at
                                ?->toIso8601String(),

                        'uploaded_at' =>
                            $photo
                                ->uploaded_at
                                ?->toIso8601String(),
                    ]
                    : [
                        'position' =>
                            $position,

                        'label' =>
                            $label,

                        'has_photo' =>
                            false,

                        'grid_enabled' =>
                            false,

                        'grid_settings' =>
                            null,

                        'observation' =>
                            null,

                        'captured_at' =>
                            null,

                        'uploaded_at' =>
                            null,
                    ];
        }

        return [
            'assessment_uuid' =>
                $assessment->uuid,

            'evaluation_date' =>
                $assessment
                    ->evaluation_date
                    ->format('Y-m-d'),

            'postural_assessment' => [
                'lateral' =>
                    $payload[
                        'lateral'
                    ] ?? [
                        'alterations' =>
                            [],
                    ],

                'posterior' =>
                    $payload[
                        'posterior'
                    ] ?? [
                        'alterations' =>
                            [],
                    ],

                'anterior' =>
                    $payload[
                        'anterior'
                    ] ?? [
                        'alterations' =>
                            [],

                        'shoulder_asymmetry' =>
                            null,

                        'hip_asymmetry' =>
                            null,
                    ],

                'observations' =>
                    $payload[
                        'observations'
                    ] ?? null,
            ],

            'photos' =>
                $photoData,

            'photo_consent' =>
                $assessment
                    ->photoConsent
                    ? [
                        'active' =>
                            $assessment
                                ->photoConsent
                                ->isActive(),

                        'storage_authorized' =>
                            $assessment
                                ->photoConsent
                                ->storage_authorized,

                        'external_use_allowed' =>
                            $assessment
                                ->photoConsent
                                ->external_use_allowed,

                        'revoked_at' =>
                            $assessment
                                ->photoConsent
                                ->revoked_at
                                ?->toIso8601String(),
                    ]
                    : null,

            'configuration' => [
                'lateral_alterations' =>
                    $this->options(
                        self::LATERAL
                    ),

                'posterior_alterations' =>
                    $this->options(
                        self::POSTERIOR
                    ),

                'anterior_alterations' =>
                    $this->options(
                        self::ANTERIOR
                    ),

                'shoulder_asymmetries' =>
                    $this->options(
                        self::SHOULDERS
                    ),

                'hip_asymmetries' =>
                    $this->options(
                        self::HIPS
                    ),

                'photo_positions' =>
                    $this->options(
                        self::PHOTO_POSITIONS
                    ),

                'symmetrograph_available' =>
                    true,
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

    private function options(
        array $options
    ): array {
        $result = [];

        foreach (
            $options as
            $key => $label
        ) {
            $result[] = [
                'key' =>
                    $key,

                'label' =>
                    $label,
            ];
        }

        return $result;
    }
}
