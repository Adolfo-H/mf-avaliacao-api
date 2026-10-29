<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentProgressPhoto;
use App\Models\Student;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Comparação fotográfica interna: somente avaliações concluídas, do mesmo
 * aluno, autorizadas ao profissional e com consentimento vigente em AMBAS.
 * Não entrega caminhos de arquivos ou URLs públicas.
 */
final class StudentAssessmentPhotoComparisonController extends Controller
{
    private const POSITIONS = [
        'front' => 'Frontal',
        'back_flexed' => 'Costas contraído',
        'front_flexed' => 'Frontal contraído',
        'side' => 'Lateral',
    ];

    public function show(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();

        if (! $user || (! $user->isAdmin() && ! $user->isEvaluator())) {
            abort(403, 'Você não possui permissão para acessar fotografias clínicas.');
        }

        $validated = $request->validate([
            'first_uuid' => ['required', 'uuid', 'different:second_uuid'],
            'second_uuid' => ['required', 'uuid', 'different:first_uuid'],
        ]);

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
            ->with(['evaluator:id,name', 'photoConsent', 'progressPhotos'])
            ->get();

        // 404 evita revelar a existência de registros de outros avaliadores.
        if ($assessments->count() !== 2) {
            abort(404, 'Uma das avaliações não foi encontrada ou não está autorizada.');
        }

        foreach ($assessments as $assessment) {
            if (! $assessment->isCompleted()) {
                abort(422, 'Somente avaliações concluídas podem ser comparadas.');
            }
        }

        // Consentimento deve ser verificado a cada solicitação (sem cache).
        // Uso externo não é necessário para visualização CLÍNICA interna.
        foreach ($assessments as $assessment) {
            if (! $assessment->photoConsent?->isActive()) {
                abort(403, 'Comparação bloqueada: consentimento fotográfico inativo ou revogado.');
            }
        }

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

        $basePhotos = $base->progressPhotos->keyBy('position');
        $targetPhotos = $target->progressPhotos->keyBy('position');
        $positions = [];
        $availablePairs = 0;

        foreach (self::POSITIONS as $position => $label) {
            /** @var AssessmentProgressPhoto|null $basePhoto */
            $basePhoto = $basePhotos->get($position);
            /** @var AssessmentProgressPhoto|null $targetPhoto */
            $targetPhoto = $targetPhotos->get($position);
            $bothAvailable = $basePhoto !== null && $targetPhoto !== null;

            if ($bothAvailable) {
                $availablePairs++;
            }

            $positions[] = [
                'position' => $position,
                'label' => $label,
                'both_available' => $bothAvailable,
                'base' => $this->photoInfo($basePhoto),
                'target' => $this->photoInfo($targetPhoto),
            ];
        }

        // Auditoria SOMENTE com identificadores, nunca foto, observação ou caminho.
        AuditLogger::record(
            $request,
            'student.assessment_photo_comparison.view',
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
                'positions' => $positions,
                'available_pairs' => $availablePairs,
                'note' => 'Comparação visual interna. Diferenças de ângulo, luz e distância não permitem conclusões clínicas isoladas.',
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    /** @return array<string, mixed> */
    private function assessmentInfo(Assessment $assessment): array
    {
        return [
            'uuid' => $assessment->uuid,
            'evaluation_date' => $assessment->evaluation_date->format('Y-m-d'),
            'status' => $assessment->status->value,
            'evaluator' => [
                'id' => $assessment->evaluator?->id,
                'name' => $assessment->evaluator?->name,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function photoInfo(?AssessmentProgressPhoto $photo): array
    {
        return [
            'has_photo' => $photo !== null,
            'captured_at' => $photo?->captured_at?->toIso8601String(),
            'observation' => $photo?->observation,
        ];
    }
}
