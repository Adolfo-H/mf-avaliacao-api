<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AssessmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Student;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Indicadores operacionais reais. Não inclui dados clínicos na resposta nem no log.
 * Recepção recebe somente a quantidade administrativa de alunos ativos.
 */
final class DashboardSummaryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $user->active) {
            abort(403, 'Você não possui permissão para consultar este painel.');
        }

        if (! $user->isAdmin() && ! $user->isEvaluator() && ! $user->isReception()) {
            abort(403, 'Perfil sem permissão para consultar o painel.');
        }

        // Não apresentar quantidade de avaliações clínicas à Recepção.
        if ($user->isReception()) {
            AuditLogger::record(
                $request,
                'dashboard.summary.view',
                metadata: ['scope' => 'reception', 'clinical_access' => false],
            );

            return response()->json([
                'data' => [
                    'scope' => 'reception',
                    'students' => [
                        'count' => Student::query()->active()->count(),
                        'label' => 'Alunos ativos',
                    ],
                    'assessments' => null,
                    'recent' => [],
                    'permissions' => ['can_view_assessments' => false],
                    'generated_at' => now()->toIso8601String(),
                ],
            ]);
        }

        // Consulta única de base, com escopo por avaliador. Evitar eager load
        // de seções em contagens para não carregar informações clínicas.
        $assessments = Assessment::query()
            ->without('sections')
            ->when(
                $user->isEvaluator(),
                fn ($query) => $query->where('evaluator_id', $user->id),
            );

        $studentsCount = $user->isAdmin()
            ? Student::query()->active()->count()
            : (clone $assessments)
                ->whereHas('student', fn ($query) => $query->active())
                ->distinct()
                ->count('student_id');

        $recent = (clone $assessments)
            ->with('student:id,uuid,name')
            ->orderByDesc('evaluation_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (Assessment $assessment): array => [
                'uuid' => $assessment->uuid,
                'evaluation_date' => $assessment->evaluation_date?->format('Y-m-d'),
                'status' => $assessment->status->value,
                'status_label' => $assessment->status->label(),
                'student' => [
                    'uuid' => $assessment->student?->uuid,
                    'name' => $assessment->student?->name,
                ],
            ])
            ->values()
            ->all();

        AuditLogger::record(
            $request,
            'dashboard.summary.view',
            metadata: [
                'scope' => $user->isAdmin() ? 'admin' : 'evaluator',
                'clinical_access' => true,
                'recent_count' => count($recent),
            ],
        );

        return response()->json([
            'data' => [
                'scope' => $user->isAdmin() ? 'admin' : 'evaluator',
                'students' => [
                    'count' => $studentsCount,
                    'label' => $user->isAdmin() ? 'Alunos ativos' : 'Alunos acompanhados',
                ],
                'assessments' => [
                    'total' => (clone $assessments)->count(),
                    'draft' => (clone $assessments)->where('status', AssessmentStatus::Draft->value)->count(),
                    'completed' => (clone $assessments)->where('status', AssessmentStatus::Completed->value)->count(),
                ],
                'recent' => $recent,
                'permissions' => ['can_view_assessments' => true],
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
