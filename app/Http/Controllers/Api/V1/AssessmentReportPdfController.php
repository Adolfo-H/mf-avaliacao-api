<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AssessmentSectionType;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Student;
use App\Support\AuditLogger;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Versão inicial do relatório: medidas comprovadamente armazenadas,
 * histórico numérico e status das sete seções. Sem laudo, classificação
 * clínica ou imagens. PDFs NÃO são gravados nem publicados pelo servidor.
 */
final class AssessmentReportPdfController extends Controller
{
    private const CIRCUMFERENCES = [
        ['Cintura', 'waist'],
        ['Quadril', 'hip'],
        ['Abdômen', 'abdomen'],
        ['Tórax', 'chest'],
        ['Ombros', 'shoulder'],
        ['Pescoço', 'neck'],
        ['Antebraço direito', 'forearm.right'],
        ['Antebraço esquerdo', 'forearm.left'],
        ['Braço relaxado direito', 'relaxed_arm.right'],
        ['Braço relaxado esquerdo', 'relaxed_arm.left'],
        ['Braço flexionado direito', 'flexed_arm.right'],
        ['Braço flexionado esquerdo', 'flexed_arm.left'],
        ['Coxa proximal direita', 'proximal_thigh.right'],
        ['Coxa proximal esquerda', 'proximal_thigh.left'],
        ['Coxa medial direita', 'medial_thigh.right'],
        ['Coxa medial esquerda', 'medial_thigh.left'],
        ['Coxa distal direita', 'distal_thigh.right'],
        ['Coxa distal esquerda', 'distal_thigh.left'],
        ['Panturrilha direita', 'calf.right'],
        ['Panturrilha esquerda', 'calf.left'],
    ];

    public function show(
        Request $request,
        Student $student,
        Assessment $assessment
    ): Response {
        $user = $request->user();
        if (! $user || (! $user->isAdmin() && ! $user->isEvaluator())) {
            abort(403, 'Acesso a relatórios clínicos não autorizado.');
        }

        // A rota usa {assessment}, também protegida pelo middleware
        // EnsureAssessmentAccess. A verificação abaixo evita bypass
        // se o controller for chamado em outro contexto.
        if ($assessment->student_id !== $student->id) {
            abort(404, 'Avaliação não encontrada para este aluno.');
        }
        if ($user->isEvaluator() && $assessment->evaluator_id !== $user->id) {
            abort(403, 'Você não pode acessar a avaliação de outro profissional.');
        }
        if (! $assessment->isCompleted()) {
            abort(422, 'Somente avaliações concluídas geram relatórios.');
        }

        $assessment->load([
            'evaluator:id,name',
            'bodyComposition',
            'anthropometry',
            'sections',
        ]);

        $composition = $assessment->bodyComposition?->payload ?? [];
        $compositionResults = $assessment->bodyComposition?->results ?? [];
        $perimeters = $assessment->anthropometry?->payload ?? [];
        $perimeterResults = $assessment->anthropometry?->results ?? [];

        $summary = [
            ['label' => 'Peso', 'value' => $this->number(data_get($composition, 'weight_kg')), 'unit' => 'kg'],
            ['label' => 'Altura', 'value' => $this->number(data_get($composition, 'height_m')), 'unit' => 'm'],
            ['label' => 'IMC', 'value' => $this->number(data_get($compositionResults, 'bmi.value')), 'unit' => ''],
            ['label' => 'RCQ', 'value' => $this->number(data_get($perimeterResults, 'waist_to_hip_ratio.value'), 4), 'unit' => ''],
        ];
        $circumferences = [];
        foreach (self::CIRCUMFERENCES as [$label, $path]) {
            $circumferences[] = [
                'label' => $label,
                'value' => $this->number(data_get($perimeters, 'circumferences.'.$path)),
            ];
        }

        $sections = [];
        foreach (AssessmentSectionType::cases() as $type) {
            $item = $assessment->sections->first(
                fn ($section): bool => $section->section === $type
            );
            $sections[] = [
                'label' => $type->label(),
                'status' => $item?->status?->label() ?? 'Não informada',
            ];
        }

        // No relatório de uma avaliação histórica, não mostrar avaliações
        // posteriores à data dela. Escopo por avaliador permanece ativo.
        $history = Assessment::query()
            ->without('sections')
            ->where('student_id', $student->id)
            ->where('status', 'completed')
            ->whereDate('evaluation_date', '<=', $assessment->evaluation_date->toDateString())
            ->when($user->isEvaluator(), fn ($query) => $query->where('evaluator_id', $user->id))
            ->with(['bodyComposition', 'anthropometry'])
            ->orderByDesc('evaluation_date')
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->reverse()
            ->values();

        $historyRows = $history->map(function (Assessment $item): array {
            $payload = $item->bodyComposition?->payload ?? [];
            $results = $item->bodyComposition?->results ?? [];
            $anthropometry = $item->anthropometry?->payload ?? [];
            $rawWeight = data_get($payload, 'weight_kg');
            return [
                'date' => $item->evaluation_date?->format('d/m/Y') ?? '—',
                'weight' => $this->number($rawWeight),
                'weight_numeric' => $this->numeric($rawWeight),
                'bmi' => $this->number(data_get($results, 'bmi.value')),
                'waist' => $this->number(data_get($anthropometry, 'circumferences.waist')),
            ];
        })->all();

        $weights = array_filter(array_column($historyRows, 'weight_numeric'), fn ($value): bool => $value !== null);
        $maxWeight = $weights === [] ? null : max($weights);
        foreach ($historyRows as &$row) {
            $row['bar_percent'] = $maxWeight !== null && $maxWeight > 0 && $row['weight_numeric'] !== null
                ? round(100 * $row['weight_numeric'] / $maxWeight, 1)
                : null;
            unset($row['weight_numeric']);
        }
        unset($row);

        $html = view('reports.assessment', [
            'student' => $student,
            'assessment' => $assessment,
            'generatedAt' => now()->format('d/m/Y H:i'),
            'age' => $student->ageAt($assessment->evaluation_date),
            'summary' => $summary,
            'circumferences' => $circumferences,
            'sections' => $sections,
            'historyRows' => $historyRows,
        ])->render();

        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();
        $binary = $dompdf->output();

        // Os metadados da auditoria NUNCA carregam medidas ou notas clínicas.
        AuditLogger::record(
            $request,
            'assessment.report_pdf.download',
            subject: $assessment,
            assessmentId: $assessment->id,
            studentId: $student->id,
            metadata: ['report_version' => '1.0', 'photos_included' => false]
        );

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="mf-avaliacao-'.$assessment->uuid.'.pdf"',
            'Cache-Control' => 'private, no-store',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function numeric(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }
        $number = (float) $value;
        return is_finite($number) ? $number : null;
    }

    private function number(mixed $value, int $digits = 2): string
    {
        $number = $this->numeric($value);
        return $number === null ? '—' : number_format($number, $digits, ',', '.');
    }
}
