<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Support\AuditLogger;
use App\Support\ClinicalSkinfoldPreviewCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Prévia de uso interno. Sem writes em avaliações, sem classificação clínica. */
final class AssessmentClinicalPreviewController extends Controller
{
    public function show(
        Request $request,
        Assessment $assessment,
        ClinicalSkinfoldPreviewCalculator $calculator
    ): JsonResponse {
        $user = $request->user();
        if (! $user || (! $user->isAdmin() && ! $user->isEvaluator())) {
            abort(403, 'Prévia clínica não autorizada.');
        }
        if ($user->isEvaluator() && $assessment->evaluator_id !== $user->id) {
            abort(403, 'Prévia clínica não autorizada para esta avaliação.');
        }

        $assessment->load(['student', 'bodyComposition']);
        $payload = $assessment->bodyComposition?->payload ?? [];
        $preview = $calculator->evaluate(
            $payload['protocol'] ?? null,
            $assessment->student?->sex,
            $assessment->ageAtEvaluation(),
            is_array($payload['skinfolds'] ?? null) ? $payload['skinfolds'] : []
        );

        // Registrar apenas metadados técnicos; nunca medidas, idade, sexo ou resultado.
        AuditLogger::record(
            $request,
            'assessment.clinical_preview.view',
            subject: $assessment,
            assessmentId: $assessment->id,
            studentId: $assessment->student_id,
            metadata: [
                'formula_version' => ClinicalSkinfoldPreviewCalculator::VERSION,
                'preview_status' => $preview['status'],
            ]
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
