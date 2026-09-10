<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UploadStudentPhotoRequest;
use App\Http\Resources\Api\V1\StudentResource;
use App\Models\Student;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class StudentPhotoController extends Controller
{
    public function store(
        UploadStudentPhotoRequest $request,
        Student $student
    ): StudentResource {
        $diskName = (string) config(
            'student-photos.disk',
            'student_photos_local'
        );

        $disk = Storage::disk($diskName);

        $base64 = $request->string(
            'photo_base64'
        )->toString();

        $binary = base64_decode(
            $base64,
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

        if (strlen($binary) > $maxBytes) {
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

        $newPath = sprintf(
            'students/%s/%s.jpg',
            $student->uuid,
            Str::uuid()
        );

        $oldPath = $student->photo_path;

        try {
            $stored = $disk->put(
                $newPath,
                $binary,
                [
                    'visibility' => 'private',
                ]
            );

            if (! $stored) {
                abort(
                    500,
                    'Não foi possível armazenar a fotografia.'
                );
            }

            $student->update([
                'photo_path' => $newPath,

                'updated_by' =>
                    $request->user()->id,
            ]);
        } catch (Throwable $exception) {
            if ($disk->exists($newPath)) {
                $disk->delete($newPath);
            }

            throw $exception;
        }

        if (
            $oldPath &&
            $oldPath !== $newPath &&
            $disk->exists($oldPath)
        ) {
            $disk->delete($oldPath);
        }

        return new StudentResource(
            $student->fresh()
        );
    }

    public function show(
        Student $student
    ): StreamedResponse {
        if (! $student->photo_path) {
            abort(
                404,
                'O aluno não possui fotografia.'
            );
        }

        $disk = Storage::disk(
            (string) config(
                'student-photos.disk',
                'student_photos_local'
            )
        );

        if (
            ! $disk->exists(
                $student->photo_path
            )
        ) {
            abort(
                404,
                'Fotografia não encontrada.'
            );
        }

        return $disk->response(
            $student->photo_path,
            null,
            [
                'Cache-Control' =>
                    'private, max-age=300',

                'X-Content-Type-Options' =>
                    'nosniff',
            ]
        );
    }

    public function destroy(
        Student $student
    ): StudentResource {
        $oldPath = $student->photo_path;

        if (! $oldPath) {
            return new StudentResource(
                $student
            );
        }

        $disk = Storage::disk(
            (string) config(
                'student-photos.disk',
                'student_photos_local'
            )
        );

        $student->update([
            'photo_path' => null,

            'updated_by' =>
                request()->user()->id,
        ]);

        if ($disk->exists($oldPath)) {
            $disk->delete($oldPath);
        }

        return new StudentResource(
            $student->fresh()
        );
    }
}
