<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Support\AuditLogger;
use App\Support\RockportOneMilePreviewCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Prévia matemática autenticada, sem gravação ou classificação clínica. */
final class AssessmentRockportPreviewController extends Controller
{
    public function show(
        Request $request,
        Assessment $assessment,
        RockportOneMilePreviewCalculator $calculator
    ): JsonResponse {
        $user = $request->user();
        if (! $user || (! $user->isAdmin() && ! $user->isEvaluator())) {
            abort(403, 'Acesso à prévia clínica não autorizado.');
        }
        // Escopo redundante ao middleware EnsureAssessmentAccess (rota {assessment}).
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
            $test['heart_rate_bpm'] ?? null,
            $test['weight_kg'] ?? null,
            $assessment->ageAtEvaluation(),
            $assessment->student?->sex
        );

        // Nunca registrar peso, idade, sexo, FC, tempo ou VO2 em metadados de auditoria.
        AuditLogger::record(
            $request,
            'assessment.vo2_rockport_preview.view',
            subject: $assessment,
            assessmentId: $assessment->id,
            studentId: $assessment->student_id,
            metadata: [
                'formula_version' => RockportOneMilePreviewCalculator::VERSION,
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
