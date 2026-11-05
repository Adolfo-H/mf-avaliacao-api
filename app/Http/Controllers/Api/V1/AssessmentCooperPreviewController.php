<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Support\AuditLogger;
use App\Support\Cooper12MinutePreviewCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Consulta autenticada, somente leitura, sem classificação ou resultado oficial. */
final class AssessmentCooperPreviewController extends Controller
{
    public function show(
        Request $request,
        Assessment $assessment,
        Cooper12MinutePreviewCalculator $calculator
    ): JsonResponse {
        $user = $request->user();
        if (! $user || (! $user->isAdmin() && ! $user->isEvaluator())) {
            abort(403, 'Você não possui permissão para consultar a prévia.');
        }
        // Proteção redundante ao middleware global EnsureAssessmentAccess.
        if ($user->isEvaluator() && $assessment->evaluator_id !== $user->id) {
            abort(403, 'Você não pode consultar esta avaliação.');
        }

        $assessment->load(['student', 'vo2Max']);
        $payload = $assessment->vo2Max?->payload ?? [];
        $test = is_array($payload['test'] ?? null) ? $payload['test'] : [];

        $preview = $calculator->evaluate(
            $payload['protocol'] ?? null,
            $test['distance_m'] ?? null,
            $test['time_seconds'] ?? null,
            $assessment->ageAtEvaluation()
        );

        AuditLogger::record(
            $request,
            'assessment.vo2_cooper_preview.view',
            subject: $assessment,
            assessmentId: $assessment->id,
            studentId: $assessment->student_id,
            metadata: [
                'formula_version' => Cooper12MinutePreviewCalculator::VERSION,
                'preview_status' => $preview['status'],
            ]
        );

        return response()->json([
            'data' => [
                'assessment_uuid' => $assessment->uuid,
                'evaluation_date' => $assessment->evaluation_date?->format('Y-m-d'),
                'preview' => $preview,
            ],
        ], 200, [
            'Cache-Control' => 'private, no-store',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
