<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Exibe dados informados pelo equipamento, sem aplicar equações ou laudos.
 * O formulário de gravação existente usa o cast encrypted:array da composição.
 */
final class AssessmentBioimpedancePreviewController extends Controller
{
    private const DEVICE_FIELDS = [
        'manufacturer',
        'model',
        'software_version',
        'measured_on',
    ];

    private const MEASUREMENTS = [
        ['key' => 'reported_weight_kg', 'label' => 'Peso informado pelo equipamento', 'unit' => 'kg'],
        ['key' => 'body_fat_percentage', 'label' => 'Gordura corporal informada', 'unit' => '%'],
        ['key' => 'fat_mass_kg', 'label' => 'Massa de gordura informada', 'unit' => 'kg'],
        ['key' => 'fat_free_mass_kg', 'label' => 'Massa livre de gordura informada', 'unit' => 'kg'],
        ['key' => 'skeletal_muscle_mass_kg', 'label' => 'Massa muscular esquelética informada', 'unit' => 'kg'],
        ['key' => 'total_body_water_percentage', 'label' => 'Água corporal informada', 'unit' => '%'],
        ['key' => 'resistance_ohm', 'label' => 'Resistência medida', 'unit' => 'Ω'],
        ['key' => 'reactance_ohm', 'label' => 'Reatância medida', 'unit' => 'Ω'],
    ];

    public function show(Request $request, Assessment $assessment): JsonResponse
    {
        $user = $request->user();
        if (! $user || (! $user->isAdmin() && ! $user->isEvaluator())) {
            abort(403, 'Acesso à composição corporal não autorizado.');
        }
        // O middleware EnsureAssessmentAccess protege {assessment};
        // este segundo bloqueio evita ampliar permissões numa chamada isolada.
        if ($user->isEvaluator() && $assessment->evaluator_id !== $user->id) {
            abort(403, 'Avaliação não autorizada.');
        }

        $assessment->load('bodyComposition');
        $payload = $assessment->bodyComposition?->payload ?? [];
        $selected = ($payload['protocol'] ?? null) === 'bioimpedance';
        $reported = $selected && is_array($payload['bioimpedance'] ?? null)
            ? $payload['bioimpedance']
            : [];

        $device = [];
        foreach (self::DEVICE_FIELDS as $field) {
            $device[$field] = $reported[$field] ?? null;
        }
        $measurements = [];
        $reportedCount = 0;
        foreach (self::MEASUREMENTS as $metric) {
            $raw = $reported[$metric['key']] ?? null;
            $value = is_numeric($raw) && is_finite((float) $raw)
                ? (float) $raw : null;
            if ($value !== null) {
                ++$reportedCount;
            }
            $measurements[] = [
                'key' => $metric['key'],
                'label' => $metric['label'],
                'unit' => $metric['unit'],
                'value' => $value,
            ];
        }

        AuditLogger::record(
            $request,
            'assessment.bioimpedance_preview.view',
            subject: $assessment,
            assessmentId: $assessment->id,
            studentId: $assessment->student_id,
            // Não registrar fabricante, medições, observações ou parâmetros clínicos.
            metadata: ['preview_version' => '08E', 'clinical_result_saved' => false],
        );

        return response()->json([
            'data' => [
                'assessment_uuid' => $assessment->uuid,
                'protocol_selected' => $selected,
                'status' => ! $selected ? 'protocol_not_selected'
                    : ($reportedCount > 0 ? 'device_values_recorded' : 'no_measurements'),
                'homologated' => false,
                'calculation_performed' => false,
                'source' => 'reported_by_device',
                'device' => $device,
                'measurements' => $measurements,
                'conditions' => $reported['conditions'] ?? null,
                'notes' => $reported['notes'] ?? null,
                'notice' => 'Os valores foram transcritos do equipamento, sem cálculo, classificação ou homologação clínica pelo sistema.',
            ],
        ], 200, [
            'Cache-Control' => 'private, no-store',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
