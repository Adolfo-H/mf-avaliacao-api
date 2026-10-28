<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Student;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Evolução longitudinal dos resultados efetivamente registrados e aprovados.
 * Não refaz fórmulas nem atribui interpretações clínicas às variações.
 */
final class StudentAssessmentEvolutionController extends Controller
{
    private const MAX_ASSESSMENTS = 200;

    /**
     * @var list<array{key: string, label: string, unit: string|null, digits: int, group: string, source: string}>
     */
    private const METRICS = [
        ['key' => 'weight_kg', 'label' => 'Peso', 'unit' => 'kg', 'digits' => 2, 'group' => 'body', 'source' => 'body.payload.weight_kg'],
        ['key' => 'height_m', 'label' => 'Altura', 'unit' => 'm', 'digits' => 2, 'group' => 'body', 'source' => 'body.payload.height_m'],
        ['key' => 'bmi', 'label' => 'IMC', 'unit' => null, 'digits' => 2, 'group' => 'body', 'source' => 'body.results.bmi.value'],

        ['key' => 'waist_cm', 'label' => 'Cintura', 'unit' => 'cm', 'digits' => 2, 'group' => 'circumference', 'source' => 'anthropometry.payload.circumferences.waist'],
        ['key' => 'hip_cm', 'label' => 'Quadril', 'unit' => 'cm', 'digits' => 2, 'group' => 'circumference', 'source' => 'anthropometry.payload.circumferences.hip'],
        ['key' => 'abdomen_cm', 'label' => 'Abdômen', 'unit' => 'cm', 'digits' => 2, 'group' => 'circumference', 'source' => 'anthropometry.payload.circumferences.abdomen'],
        ['key' => 'shoulder_cm', 'label' => 'Ombros', 'unit' => 'cm', 'digits' => 2, 'group' => 'circumference', 'source' => 'anthropometry.payload.circumferences.shoulder'],
        ['key' => 'chest_cm', 'label' => 'Tórax', 'unit' => 'cm', 'digits' => 2, 'group' => 'circumference', 'source' => 'anthropometry.payload.circumferences.chest'],
        ['key' => 'neck_cm', 'label' => 'Pescoço', 'unit' => 'cm', 'digits' => 2, 'group' => 'circumference', 'source' => 'anthropometry.payload.circumferences.neck'],

        ['key' => 'forearm_right_cm', 'label' => 'Antebraço direito', 'unit' => 'cm', 'digits' => 2, 'group' => 'circumference', 'source' => 'anthropometry.payload.circumferences.forearm.right'],
        ['key' => 'forearm_left_cm', 'label' => 'Antebraço esquerdo', 'unit' => 'cm', 'digits' => 2, 'group' => 'circumference', 'source' => 'anthropometry.payload.circumferences.forearm.left'],
        ['key' => 'relaxed_arm_right_cm', 'label' => 'Braço relaxado direito', 'unit' => 'cm', 'digits' => 2, 'group' => 'circumference', 'source' => 'anthropometry.payload.circumferences.relaxed_arm.right'],
        ['key' => 'relaxed_arm_left_cm', 'label' => 'Braço relaxado esquerdo', 'unit' => 'cm', 'digits' => 2, 'group' => 'circumference', 'source' => 'anthropometry.payload.circumferences.relaxed_arm.left'],
        ['key' => 'flexed_arm_right_cm', 'label' => 'Braço flexionado direito', 'unit' => 'cm', 'digits' => 2, 'group' => 'circumference', 'source' => 'anthropometry.payload.circumferences.flexed_arm.right'],
        ['key' => 'flexed_arm_left_cm', 'label' => 'Braço flexionado esquerdo', 'unit' => 'cm', 'digits' => 2, 'group' => 'circumference', 'source' => 'anthropometry.payload.circumferences.flexed_arm.left'],
        ['key' => 'proximal_thigh_right_cm', 'label' => 'Coxa proximal direita', 'unit' => 'cm', 'digits' => 2, 'group' => 'circumference', 'source' => 'anthropometry.payload.circumferences.proximal_thigh.right'],
        ['key' => 'proximal_thigh_left_cm', 'label' => 'Coxa proximal esquerda', 'unit' => 'cm', 'digits' => 2, 'group' => 'circumference', 'source' => 'anthropometry.payload.circumferences.proximal_thigh.left'],
        ['key' => 'medial_thigh_right_cm', 'label' => 'Coxa medial direita', 'unit' => 'cm', 'digits' => 2, 'group' => 'circumference', 'source' => 'anthropometry.payload.circumferences.medial_thigh.right'],
        ['key' => 'medial_thigh_left_cm', 'label' => 'Coxa medial esquerda', 'unit' => 'cm', 'digits' => 2, 'group' => 'circumference', 'source' => 'anthropometry.payload.circumferences.medial_thigh.left'],
        ['key' => 'distal_thigh_right_cm', 'label' => 'Coxa distal direita', 'unit' => 'cm', 'digits' => 2, 'group' => 'circumference', 'source' => 'anthropometry.payload.circumferences.distal_thigh.right'],
        ['key' => 'distal_thigh_left_cm', 'label' => 'Coxa distal esquerda', 'unit' => 'cm', 'digits' => 2, 'group' => 'circumference', 'source' => 'anthropometry.payload.circumferences.distal_thigh.left'],
        ['key' => 'calf_right_cm', 'label' => 'Panturrilha direita', 'unit' => 'cm', 'digits' => 2, 'group' => 'circumference', 'source' => 'anthropometry.payload.circumferences.calf.right'],
        ['key' => 'calf_left_cm', 'label' => 'Panturrilha esquerda', 'unit' => 'cm', 'digits' => 2, 'group' => 'circumference', 'source' => 'anthropometry.payload.circumferences.calf.left'],

        ['key' => 'waist_to_hip_ratio', 'label' => 'RCQ', 'unit' => null, 'digits' => 4, 'group' => 'ratio', 'source' => 'anthropometry.results.waist_to_hip_ratio.value'],
    ];

    public function index(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();

        if (! $user || (! $user->isAdmin() && ! $user->isEvaluator())) {
            abort(403, 'Você não possui permissão para consultar evolução clínica.');
        }

        $validated = $request->validate([
            'period_months' => ['sometimes', 'integer', Rule::in([3, 6, 12])],
        ]);

        $months = (int) ($validated['period_months'] ?? 6);
        $end = now()->endOfDay();
        $start = now()->subMonthsNoOverflow($months)->startOfDay();

        // Ler só o que é autorizado e concluído; sem seções e sem N+1.
        // Pegamos mais um registro para sinalizar limitação, sem retornar
        // milhares de amostras clínicas na resposta.
        $assessments = Assessment::query()
            ->without('sections')
            ->where('student_id', $student->id)
            ->when(
                $user->isEvaluator(),
                fn ($query) => $query->where('evaluator_id', $user->id)
            )
            ->completed()
            ->whereBetween('evaluation_date', [
                $start->toDateString(),
                $end->toDateString(),
            ])
            ->with(['bodyComposition', 'anthropometry'])
            ->orderByDesc('evaluation_date')
            ->orderByDesc('id')
            ->limit(self::MAX_ASSESSMENTS + 1)
            ->get();

        $hasMore = $assessments->count() > self::MAX_ASSESSMENTS;
        $ordered = $assessments
            ->take(self::MAX_ASSESSMENTS)
            ->reverse()
            ->values();

        $rows = $ordered->map(function (Assessment $assessment): array {
            return [
                'assessment_uuid' => $assessment->uuid,
                'evaluation_date' => $assessment->evaluation_date?->format('Y-m-d'),
                'source' => [
                    'body' => [
                        'payload' => $assessment->bodyComposition?->payload ?? [],
                        'results' => $assessment->bodyComposition?->results ?? [],
                    ],
                    'anthropometry' => [
                        'payload' => $assessment->anthropometry?->payload ?? [],
                        'results' => $assessment->anthropometry?->results ?? [],
                    ],
                ],
            ];
        });

        $series = [];
        foreach (self::METRICS as $metric) {
            $valid = 0;
            $points = [];
            foreach ($rows as $row) {
                $value = $this->numeric(
                    data_get($row['source'], $metric['source']),
                    $metric['digits']
                );
                if ($value !== null) {
                    $valid++;
                }
                $points[] = [
                    'assessment_uuid' => $row['assessment_uuid'],
                    'evaluation_date' => $row['evaluation_date'],
                    'value' => $value,
                ];
            }

            $series[] = [
                'key' => $metric['key'],
                'label' => $metric['label'],
                'unit' => $metric['unit'],
                'digits' => $metric['digits'],
                'group' => $metric['group'],
                'valid_points' => $valid,
                'points' => $points,
            ];
        }

        // Nunca registrar valores clínicos em texto puro no log.
        AuditLogger::record(
            $request,
            'student.assessment_evolution.view',
            subject: $student,
            studentId: $student->id,
            metadata: [
                'period_months' => $months,
                'assessment_count' => $rows->count(),
                'has_more' => $hasMore,
            ]
        );

        return response()->json([
            'data' => [
                'student' => [
                    'uuid' => $student->uuid,
                    'name' => $student->name,
                ],
                'period_months' => $months,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'assessment_count' => $rows->count(),
                'has_more' => $hasMore,
                'metrics' => $series,
                'note' => 'Valores observados em avaliações concluídas. Variações numéricas não constituem interpretação clínica. Resultados não validados não são apresentados.',
            ],
        ]);
    }

    private function numeric(mixed $value, int $digits): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $numeric = (float) $value;

        return is_finite($numeric) ? round($numeric, $digits) : null;
    }
}
