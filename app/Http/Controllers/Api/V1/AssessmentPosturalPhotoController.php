<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AssessmentSectionStatus;
use App\Enums\AssessmentSectionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateAssessmentPosturalPhotoRequest;
use App\Http\Requests\Api\V1\UploadAssessmentPosturalPhotoRequest;
use App\Models\Assessment;
use App\Models\AssessmentPosturalPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AssessmentPosturalPhotoController extends Controller
{
    private const POSITIONS = [
        'right_lateral' =>
            'Lateral direita',

        'left_lateral' =>
            'Lateral esquerda',

        'posterior' =>
            'Posterior',

        'anterior' =>
            'Anterior',
    ];

    public function store(
        UploadAssessmentPosturalPhotoRequest $request,
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

        $assessment->load(
            'photoConsent'
        );

        if (
            ! $assessment
                ->photoConsent ||
            ! $assessment
                ->photoConsent
                ->isActive()
        ) {
            abort(
                422,
                'É necessário possuir consentimento ativo para armazenamento de fotografias.'
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
                'assessments/%s/postural/%s/%s.jpg',
                $assessment->uuid,
                $position,
                Str::uuid()
            );

        $existing =
            $assessment
                ->posturalPhotos()
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
                        ->posturalPhotos()
                        ->updateOrCreate(
                            [
                                'position' =>
                                    $position,
                            ],
                            [
                                'photo_path' =>
                                    $newPath,

                                'grid_enabled' =>
                                    $data[
                                        'grid_enabled'
                                    ] ?? false,

                                'grid_settings' =>
                                    $data[
                                        'grid_settings'
                                    ] ?? null,

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
                ->posturalPhotos()
                ->where(
                    'position',
                    $position
                )
                ->firstOrFail();

        return response()->json([
            'data' =>
                $this->serialize(
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
                ->posturalPhotos()
                ->where(
                    'position',
                    $position
                )
                ->first();

        if (! $photo) {
            abort(
                404,
                'Fotografia postural não encontrada.'
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
        UpdateAssessmentPosturalPhotoRequest $request,
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
                ->posturalPhotos()
                ->where(
                    'position',
                    $position
                )
                ->firstOrFail();

        $photo->update([
            ...$request->validated(),

            'updated_by' =>
                $request
                    ->user()
                    ->id,
        ]);

        return response()->json([
            'data' =>
                $this->serialize(
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
                ->posturalPhotos()
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
                'Fotografia postural excluída.',
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
                'Posição postural inválida.'
            );
        }
    }

    private function serialize(
        AssessmentPosturalPhoto $photo
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
        ];
    }
}
