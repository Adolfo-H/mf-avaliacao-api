<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Support\AuditLogger;
use App\Support\WeltmanObesityPreviewCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint somente leitura. A medição é salva apenas pelo PUT existente
 * da composição corporal, cujo payload usa encrypted:array.
 */
final class AssessmentWeltmanPreviewController extends Controller
{
    public function show(Request $request, Assessment $assessment): JsonResponse
    {
        $user = $request->user();
        if (! $user || (! $user->isAdmin() && ! $user->isEvaluator())) {
            abort(403, 'Acesso a dados de composição corporal não autorizado.');
        }
        if ($user->isEvaluator() && $assessment->evaluator_id !== $user->id) {
            abort(403, 'Avaliação não autorizada.');
        }

        $assessment->load(['student', 'bodyComposition']);
        $payload = $assessment->bodyComposition?->payload ?? [];
        $weltman = is_array($payload['weltman'] ?? null) ? $payload['weltman'] : [];
        $selected = ($payload['protocol'] ?? null) === 'weltman';

        $preview = WeltmanObesityPreviewCalculator::preview(
            $selected,
            $assessment->student?->sex,
            $assessment->ageAtEvaluation(),
            $payload['weight_kg'] ?? null,
            $payload['height_m'] ?? null,
            $weltman['abdomen_upper_cm'] ?? null,
            $weltman['abdomen_umbilicus_cm'] ?? null,
            in_array(($weltman['applicability_confirmed'] ?? false), [true, 1, '1'], true)
        );

        AuditLogger::record(
            $request,
            'assessment.weltman_preview.view',
            subject: $assessment,
            assessmentId: $assessment->id,
            studentId: $assessment->student_id,
            // Apenas identificadores de versão. Nunca medidas ou justificativas clínicas.
            metadata: [
                'calculator_version' => WeltmanObesityPreviewCalculator::VERSION,
                'clinical_result_saved' => false,
            ]
        );

        return response()->json([
            'data' => [
                'assessment_uuid' => $assessment->uuid,
                'protocol_selected' => $selected,
                ...$preview,
            ],
        ], 200, [
            'Cache-Control' => 'private, no-store',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
