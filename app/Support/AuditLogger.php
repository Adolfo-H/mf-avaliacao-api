<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class AuditLogger
{
    /**
     * @param array<string, mixed> $metadata
     */
    public static function record(
        Request $request,
        string $action,
        ?Model $subject = null,
        ?int $assessmentId = null,
        ?int $studentId = null,
        array $metadata = [],
        ?User $actor = null
    ): AuditLog {
        $resolvedActor =
            $actor
            ?? $request->user();

        return AuditLog::create([
            'user_id' =>
                $resolvedActor?->id,

            'action' =>
                $action,

            'auditable_type' =>
                $subject
                    ? $subject::class
                    : null,

            'auditable_id' =>
                $subject?->getKey(),

            'assessment_id' =>
                $assessmentId,

            'student_id' =>
                $studentId,

            'ip_address' =>
                $request->ip(),

            'user_agent' =>
                $request->userAgent(),

            'metadata' =>
                $metadata !== []
                    ? $metadata
                    : null,

            'occurred_at' =>
                now(),
        ]);
    }
}
