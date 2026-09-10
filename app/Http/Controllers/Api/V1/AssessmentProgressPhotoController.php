<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AssessmentSectionStatus;
use App\Enums\AssessmentSectionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateAssessmentProgressPhotoRequest;
use App\Http\Requests\Api\V1\UploadAssessmentProgressPhotoRequest;
use App\Models\Assessment;
use App\Models\AssessmentProgressPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AssessmentProgressPhotoController extends Controller
{
    private const POSITIONS = [
        'front' =>
            'Frontal',

        'back_flexed' =>
            'Costas contraído',

        'front_flexed' =>
            'Frontal contraído',

        'side' =>
            'Lateral',
    ];

    public function index(
        Assessment $assessment
    ): JsonResponse {
        $assessment->load([
            'photoConsent',
            'progressPhotos',
            'sections',
        ]);

        return response()->json([
            'data' =>
                $this->buildResponse(
                    $assessment
                ),
        ]);
    }

    public function store(
        UploadAssessmentProgressPhotoRequest $request,
        Assessment $assessment,
        string $position
    ): JsonResponse {
        $this->assertPosition(
            $position
        );

        if ($assessment->isCompleted()) {
            abort(
                422,
                'Avaliações concluídas não podem ser alteradas.'
            );
        }

        $assessment->load([
            'photoConsent',
        ]);

        if (
            ! $assessment
                ->photoConsent ||
            ! $assessment
                ->photoConsent
                ->isActive()
        ) {
            abort(
                422,
                'É necessário registrar consentimento ativo com autorização de armazenamento antes de incluir fotografias.'
            );
        }

        $data =
            $request->validated();

        $binary =
            base64_decode(
                $data[
                    'photo_base64'
                ],
                true
            );

        if ($binary === false) {
            abort(
                422,
                'A fotografia enviada é inválida.'
            );
        }

        $maxBytes =
            ((int) config(
                'student-photos.max_size_kb',
                5120
            )) * 1024;

        if (
            strlen($binary) >
            $maxBytes
        ) {
            abort(
                422,
                'A fotografia pode possuir no máximo 5 MB.'
            );
        }

        $imageInfo =
            @getimagesizefromstring(
                $binary
            );

        if (
            $imageInfo === false ||
            ($imageInfo['mime'] ?? null)
                !== 'image/jpeg'
        ) {
            abort(
                422,
                'A fotografia enviada não é uma imagem JPG válida.'
            );
        }

        $disk =
            Storage::disk(
                (string) config(
                    'student-photos.disk',
                    'student_photos_local'
                )
            );

        $newPath =
            sprintf(
                'assessments/%s/progress/%s/%s.jpg',
                $assessment->uuid,
                $position,
                Str::uuid()
            );

        $existing =
            $assessment
                ->progressPhotos()
                ->where(
                    'position',
                    $position
                )
                ->first();

        $oldPath =
            $existing
                ?->photo_path;

        try {
            $stored =
                $disk->put(
                    $newPath,
                    $binary,
                    [
                        'visibility' =>
                            'private',
                    ]
                );

            if (! $stored) {
                abort(
                    500,
                    'Não foi possível armazenar a fotografia.'
                );
            }

            DB::transaction(
                function () use (
                    $assessment,
                    $request,
                    $data,
                    $position,
                    $newPath
                ): void {
                    $assessment
                        ->progressPhotos()
                        ->updateOrCreate(
                            [
                                'position' =>
                                    $position,
                            ],
                            [
                                'photo_path' =>
                                    $newPath,

                                'observation' =>
                                    $data[
                                        'observation'
                                    ] ?? null,

                                'captured_at' =>
                                    $data[
                                        'captured_at'
                                    ] ?? null,

                                'uploaded_at' =>
                                    now(),

                                'uploaded_by' =>
                                    $request
                                        ->user()
                                        ->id,

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
                                AssessmentSectionType::ProgressPhotos
                                    ->value
                            )
                            ->firstOrFail();

                    if (
                        $section->status ===
                        AssessmentSectionStatus::NotStarted
                    ) {
                        $section
                            ->changeStatus(
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
        } catch (Throwable $exception) {
            if (
                $disk->exists(
                    $newPath
                )
            ) {
                $disk->delete(
                    $newPath
                );
            }

            throw $exception;
        }

        if (
            $oldPath &&
            $oldPath !== $newPath &&
            $disk->exists(
                $oldPath
            )
        ) {
            $disk->delete(
                $oldPath
            );
        }

        $photo =
            $assessment
                ->progressPhotos()
                ->where(
                    'position',
                    $position
                )
                ->firstOrFail();

        return response()->json([
            'data' =>
                $this->serializePhoto(
                    $photo
                ),
        ]);
    }

    public function show(
        Assessment $assessment,
        string $position
    ): StreamedResponse {
        $this->assertPosition(
            $position
        );

        $photo =
            $assessment
                ->progressPhotos()
                ->where(
                    'position',
                    $position
                )
                ->first();

        if (! $photo) {
            abort(
                404,
                'Fotografia não encontrada.'
            );
        }

        $disk =
            Storage::disk(
                (string) config(
                    'student-photos.disk',
                    'student_photos_local'
                )
            );

        if (
            ! $disk->exists(
                $photo->photo_path
            )
        ) {
            abort(
                404,
                'Arquivo da fotografia não encontrado.'
            );
        }

        return $disk->response(
            $photo->photo_path,
            null,
            [
                'Cache-Control' =>
                    'private, max-age=300',

                'X-Content-Type-Options' =>
                    'nosniff',
            ]
        );
    }

    public function update(
        UpdateAssessmentProgressPhotoRequest $request,
        Assessment $assessment,
        string $position
    ): JsonResponse {
        $this->assertPosition(
            $position
        );

        if ($assessment->isCompleted()) {
            abort(
                422,
                'Avaliações concluídas não podem ser alteradas.'
            );
        }

        $photo =
            $assessment
                ->progressPhotos()
                ->where(
                    'position',
                    $position
                )
                ->firstOrFail();

        $data =
            $request->validated();

        $photo->update([
            ...$data,

            'updated_by' =>
                $request
                    ->user()
                    ->id,
        ]);

        return response()->json([
            'data' =>
                $this->serializePhoto(
                    $photo->fresh()
                ),
        ]);
    }

    public function destroy(
        Assessment $assessment,
        string $position
    ): JsonResponse {
        $this->assertPosition(
            $position
        );

        if ($assessment->isCompleted()) {
            abort(
                422,
                'Avaliações concluídas não podem ser alteradas.'
            );
        }

        $photo =
            $assessment
                ->progressPhotos()
                ->where(
                    'position',
                    $position
                )
                ->firstOrFail();

        $path =
            $photo->photo_path;

        $photo->delete();

        $disk =
            Storage::disk(
                (string) config(
                    'student-photos.disk',
                    'student_photos_local'
                )
            );

        if (
            $disk->exists(
                $path
            )
        ) {
            $disk->delete(
                $path
            );
        }

        return response()->json([
            'message' =>
                'Fotografia excluída.',
        ]);
    }

    private function assertPosition(
        string $position
    ): void {
        if (
            ! array_key_exists(
                $position,
                self::POSITIONS
            )
        ) {
            abort(
                404,
                'Posição de fotografia inválida.'
            );
        }
    }

    private function serializePhoto(
        AssessmentProgressPhoto $photo
    ): array {
        return [
            'position' =>
                $photo->position,

            'label' =>
                self::POSITIONS[
                    $photo->position
                ],

            'has_photo' =>
                true,

            'observation' =>
                $photo->observation,

            'captured_at' =>
                $photo
                    ->captured_at
                    ?->toIso8601String(),

            'uploaded_at' =>
                $photo
                    ->uploaded_at
                    ?->toIso8601String(),
        ];
    }

    private function buildPositions(
        Assessment $assessment
    ): array {
        $photos =
            $assessment
                ->progressPhotos
                ->keyBy(
                    'position'
                );

        $result = [];

        foreach (
            self::POSITIONS as
            $position => $label
        ) {
            $photo =
                $photos->get(
                    $position
                );

            $result[] =
                $photo
                    ? $this
                        ->serializePhoto(
                            $photo
                        )
                    : [
                        'position' =>
                            $position,

                        'label' =>
                            $label,

                        'has_photo' =>
                            false,

                        'observation' =>
                            null,

                        'captured_at' =>
                            null,

                        'uploaded_at' =>
                            null,
                    ];
        }

        return $result;
    }

    private function buildResponse(
        Assessment $assessment
    ): array {
        $consent =
            $assessment
                ->photoConsent;

        $section =
            $assessment
                ->sections
                ->first(
                    fn ($item): bool =>
                        $item->section ===
                        AssessmentSectionType::ProgressPhotos
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
                    'progressPhotos'
                )
                ->with(
                    'progressPhotos'
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

            'evaluation_date' =>
                $assessment
                    ->evaluation_date
                    ->format('Y-m-d'),

            'consent' =>
                $consent
                    ? [
                        'purpose' =>
                            $consent
                                ->purpose,

                        'consent_date' =>
                            $consent
                                ->consent_date
                                ->format(
                                    'Y-m-d'
                                ),

                        'external_use_allowed' =>
                            $consent
                                ->external_use_allowed,

                        'storage_authorized' =>
                            $consent
                                ->storage_authorized,

                        'revoked_at' =>
                            $consent
                                ->revoked_at
                                ?->toIso8601String(),

                        'active' =>
                            $consent
                                ->isActive(),
                    ]
                    : null,

            'positions' =>
                $this->buildPositions(
                    $assessment
                ),

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

                        'positions' =>
                            $this
                                ->buildPositions(
                                    $previous
                                ),
                    ]
                    : null,

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
