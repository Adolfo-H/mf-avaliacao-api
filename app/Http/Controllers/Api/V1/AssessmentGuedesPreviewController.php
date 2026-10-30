<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Support\AuditLogger;
use App\Support\GuedesThreeSkinfoldPreviewCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Somente leitura. A prévia não modifica resultados clínicos ou relatórios. */
final class AssessmentGuedesPreviewController extends Controller
{
    public function show(
        Request $request,
        Assessment $assessment,
        GuedesThreeSkinfoldPreviewCalculator $calculator,
    ): JsonResponse {
        $user = $request->user();
        if (! $user || (! $user->isAdmin() && ! $user->isEvaluator())) {
            abort(403, 'Prévia clínica não autorizada.');
        }
        if ($user->isEvaluator() && $assessment->evaluator_id !== $user->id) {
            abort(403, 'Esta avaliação pertence a outro profissional.');
        }

        $assessment->load(['student', 'bodyComposition']);
        $payload = $assessment->bodyComposition?->payload ?? [];
        $preview = $calculator->evaluate(
            $payload['protocol'] ?? null,
            $assessment->student?->sex,
            $assessment->ageAtEvaluation(),
            is_array($payload['skinfolds'] ?? null) ? $payload['skinfolds'] : [],
        );

        // Nunca incluir valores clínicos, sexo, idade ou dobras na auditoria.
        AuditLogger::record(
            $request,
            'assessment.guedes_preview.view',
            subject: $assessment,
            assessmentId: $assessment->id,
            studentId: $assessment->student_id,
            metadata: [
                'formula_version' => GuedesThreeSkinfoldPreviewCalculator::VERSION,
                'preview_status' => $preview['status'],
            ],
        );

        return response()->json([
            'data' => [
                'assessment_uuid' => $assessment->uuid,
                'evaluation_date' => $assessment->evaluation_date?->toDateString(),
                'preview' => $preview,
            ],
        ], 200, [
            'Cache-Control' => 'private, no-store',
            'Pragma' => 'no-cache',
        ]);
    }
}
