<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AssessmentSectionStatus;
use App\Enums\AssessmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Student;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentAssessmentHistoryController extends Controller
{
    public function index(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();

        if (! $user || (! $user->isAdmin() && ! $user->isEvaluator())) {
            abort(403, 'Você não possui permissão para acessar históricos clínicos.');
        }

        $filters = $request->validate([
            'status' => [
                'sometimes',
                Rule::enum(AssessmentStatus::class),
            ],
            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:50',
            ],
            'page' => [
                'sometimes',
                'integer',
                'min:1',
            ],
        ]);

        $statusFilter = $filters['status'] ?? null;
        $perPage = (int) ($filters['per_page'] ?? 15);

        $paginator = Assessment::query()
            ->where('student_id', $student->id)
            ->when(
                $user->isEvaluator(),
                fn ($query) => $query->where('evaluator_id', $user->id)
            )
            ->when(
                $statusFilter !== null,
                fn ($query) => $query->where('status', $statusFilter)
            )
            ->with([
                'evaluator:id,name',
                'bodyComposition',
                'sections',
            ])
            ->orderByDesc('evaluation_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $items = $paginator->getCollection()
            ->map(
                function (Assessment $assessment) use ($student): array {
                    $composition = $assessment->bodyComposition;
                    $payload = $composition?->payload ?? [];
                    $results = $composition?->results ?? [];

                    $completedSections = $assessment->sections
                        ->filter(
                            fn ($section): bool => $section->status ===
                                AssessmentSectionStatus::Completed
                        )
                        ->count();
                    $totalSections = $assessment->sections->count();

                    $bmiValue = data_get($results, 'bmi.value');
                    $bodyFatValue = data_get($results, 'body_fat_percentage.value')
                        ?? data_get($results, 'body_fat_percentage');
                    $status = $assessment->status;

                    return [
                        'uuid' => $assessment->uuid,
                        'evaluation_date' => $assessment->evaluation_date?->format('Y-m-d'),
                        'status' => $status instanceof AssessmentStatus
                            ? $status->value : (string) $status,
                        'status_label' => $status instanceof AssessmentStatus
                            ? $status->label() : null,
                        'completed_at' => $assessment->completed_at?->toIso8601String(),
                        'age_at_evaluation' => $student->ageAt(
                            $assessment->evaluation_date
                        ),
                        'evaluator' => [
                            'id' => $assessment->evaluator?->id,
                            'name' => $assessment->evaluator?->name,
                        ],
                        'weight_kg' => isset($payload['weight_kg'])
                            ? (float) $payload['weight_kg'] : null,
                        'height_m' => isset($payload['height_m'])
                            ? (float) $payload['height_m'] : null,
                        'bmi' => is_numeric($bmiValue)
                            ? (float) $bmiValue : null,
                        'body_fat_percentage' => is_numeric($bodyFatValue)
                            ? (float) $bodyFatValue : null,
                        'protocol' => $payload['protocol'] ?? null,
                        'progress' => [
                            'completed' => $completedSections,
                            'total' => $totalSections,
                            'percentage' => $totalSections > 0
                                ? (int) round($completedSections * 100 / $totalSections)
                                : 0,
                        ],
                    ];
                }
            )
            ->values()
            ->all();

        // Registra acesso sem incluir conteúdo clínico nos metadados do log.
        AuditLogger::record(
            $request,
            'student.assessment_history.view',
            subject: $student,
            studentId: $student->id,
            metadata: [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'status' => $statusFilter,
                'returned_count' => count($items),
            ]
        );

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
