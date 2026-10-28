<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Student;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Compara medidas observadas em duas avaliações CONCLUÍDAS do mesmo aluno.
 * Não classifica as mudanças como melhorias/pioras clínicas.
 */
final class StudentAssessmentComparisonController extends Controller
{
    private const METRICS = [
        ['key' => 'weight_kg', 'label' => 'Peso', 'unit' => 'kg', 'digits' => 2],
        ['key' => 'height_m', 'label' => 'Altura', 'unit' => 'm', 'digits' => 2],
        ['key' => 'bmi', 'label' => 'IMC', 'unit' => null, 'digits' => 2],
        ['key' => 'body_fat_percentage', 'label' => 'Gordura corporal', 'unit' => '%', 'digits' => 2],
        ['key' => 'waist_cm', 'label' => 'Cintura', 'unit' => 'cm', 'digits' => 2],
        ['key' => 'hip_cm', 'label' => 'Quadril', 'unit' => 'cm', 'digits' => 2],
        ['key' => 'waist_to_hip_ratio', 'label' => 'RCQ', 'unit' => null, 'digits' => 4],
    ];

    public function show(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();

        if (! $user || (! $user->isAdmin() && ! $user->isEvaluator())) {
            abort(403, 'Você não possui permissão para comparar avaliações clínicas.');
        }

        $validated = $request->validate([
            'first_uuid' => ['required', 'uuid', 'different:second_uuid'],
            'second_uuid' => ['required', 'uuid', 'different:first_uuid'],
        ]);

        // Apenas avaliações do aluno solicitado E autorizadas ao usuário.
        // Avaliações de outros profissionais não têm existência revelada.
        $assessments = Assessment::query()
            ->where('student_id', $student->id)
            ->when(
                $user->isEvaluator(),
                fn ($query) => $query->where('evaluator_id', $user->id)
            )
            ->whereIn('uuid', [
                $validated['first_uuid'],
                $validated['second_uuid'],
            ])
            ->with([
                'evaluator:id,name',
                'bodyComposition',
                'anthropometry',
            ])
            ->get();

        if ($assessments->count() !== 2) {
            abort(404, 'Uma das avaliações não foi encontrada ou não está autorizada.');
        }

        foreach ($assessments as $assessment) {
            if (! $assessment->isCompleted()) {
                abort(422, 'Somente avaliações concluídas podem ser comparadas.');
            }
        }

        // Ordem cronológica: a anterior é a base, independentemente da
        // ordem em que o usuário selecionou as duas avaliações.
        $ordered = $assessments
            ->sort(function (Assessment $left, Assessment $right): int {
                $dateComparison = $left->evaluation_date->format('Y-m-d')
                    <=> $right->evaluation_date->format('Y-m-d');

                return $dateComparison !== 0
                    ? $dateComparison
                    : ($left->id <=> $right->id);
            })
            ->values();

        /** @var Assessment $base */
        $base = $ordered[0];
        /** @var Assessment $target */
        $target = $ordered[1];

        $baseValues = $this->readMetrics($base);
        $targetValues = $this->readMetrics($target);
        $metrics = [];

        foreach (self::METRICS as $definition) {
            $key = $definition['key'];
            $digits = $definition['digits'];
            $baseValue = $this->numeric($baseValues[$key], $digits);
            $targetValue = $this->numeric($targetValues[$key], $digits);

            $metrics[] = [
                'key' => $key,
                'label' => $definition['label'],
                'unit' => $definition['unit'],
                'base' => $baseValue,
                'target' => $targetValue,
                'delta' => $baseValue !== null && $targetValue !== null
                    ? round($targetValue - $baseValue, $digits)
                    : null,
            ];
        }

        // Não colocar peso, exames ou anotações clínicas nos metadados.
        AuditLogger::record(
            $request,
            'student.assessment_comparison.view',
            subject: $student,
            studentId: $student->id,
            metadata: [
                'base_uuid' => $base->uuid,
                'target_uuid' => $target->uuid,
            ]
        );

        return response()->json([
            'data' => [
                'student' => [
                    'uuid' => $student->uuid,
                    'name' => $student->name,
                ],
                'base' => $this->assessmentInfo($base),
                'target' => $this->assessmentInfo($target),
                'metrics' => $metrics,
                'note' => 'As variações são apenas numéricas; não representam interpretação clínica.',
            ],
        ]);
    }

    private function assessmentInfo(Assessment $assessment): array
    {
        $payload = $assessment->bodyComposition?->payload ?? [];

        return [
            'uuid' => $assessment->uuid,
            'evaluation_date' => $assessment->evaluation_date->format('Y-m-d'),
            'status' => $assessment->status->value,
            'evaluator' => [
                'id' => $assessment->evaluator?->id,
                'name' => $assessment->evaluator?->name,
            ],
            'protocol' => $payload['protocol'] ?? null,
        ];
    }

    private function readMetrics(Assessment $assessment): array
    {
        $composition = $assessment->bodyComposition;
        $payload = $composition?->payload ?? [];
        $results = $composition?->results ?? [];

        $anthropometry = $assessment->anthropometry;
        $measurements = $anthropometry?->payload ?? [];
        $measurementResults = $anthropometry?->results ?? [];

        return [
            'weight_kg' => $payload['weight_kg'] ?? null,
            'height_m' => $payload['height_m'] ?? null,
            'bmi' => data_get($results, 'bmi.value'),
            'body_fat_percentage' => data_get($results, 'body_fat_percentage.value')
                ?? data_get($results, 'body_fat_percentage'),
            'waist_cm' => data_get($measurements, 'circumferences.waist'),
            'hip_cm' => data_get($measurements, 'circumferences.hip'),
            'waist_to_hip_ratio' => data_get($measurementResults, 'waist_to_hip_ratio.value'),
        ];
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
