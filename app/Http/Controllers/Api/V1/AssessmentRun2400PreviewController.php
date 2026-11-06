<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Support\AuditLogger;
use App\Support\Run2400MeterPreviewCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Prévia de VO2 indireto: autenticação, escopo e auditoria, sem modificar dados. */
final class AssessmentRun2400PreviewController extends Controller
{
    public function show(
        Request $request,
        Assessment $assessment,
        Run2400MeterPreviewCalculator $calculator
    ): JsonResponse {
        $user = $request->user();
        if (! $user || (! $user->isAdmin() && ! $user->isEvaluator())) {
            abort(403, 'Você não possui permissão para consultar a prévia.');
        }
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
            $assessment->ageAtEvaluation(),
        );

        // Nunca incluir distância, tempo, VO2, idade ou outros dados clínicos no log.
        AuditLogger::record(
            $request,
            'assessment.vo2_2400_preview.view',
            subject: $assessment,
            assessmentId: $assessment->id,
            studentId: $assessment->student_id,
            metadata: [
                'formula_version' => Run2400MeterPreviewCalculator::VERSION,
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
