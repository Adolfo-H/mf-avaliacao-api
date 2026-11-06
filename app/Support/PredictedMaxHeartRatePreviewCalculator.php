<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Estimativa da FC máxima pela equação de Tanaka et al. (2001).
 * Referência: J Am Coll Cardiol. 2001;37(1):153–156. PMID 11153730.
 * Apenas prévia matemática, NÃO serve isoladamente para prescrever intensidade
 * de exercício e não é persistida nos resultados clínicos oficiais.
 */
final class PredictedMaxHeartRatePreviewCalculator
{
    public const VERSION = 'tanaka-2001-hrmax-preview-v1';

    /** @return array<string, mixed> */
    public function evaluate(?int $age): array
    {
        $result = [
            'status' => 'missing_age',
            'formula_version' => self::VERSION,
            'formula' => '208 - 0.7 * idade_em_anos',
            'estimated_bpm' => null,
            'unit' => 'bpm',
            'reference' => 'Tanaka H, Monahan KD, Seals DR. JACC 2001;37(1):153-156',
            'reference_url' => 'https://pubmed.ncbi.nlm.nih.gov/11153730/',
            'professionally_approved' => false,
            'persisted' => false,
            'warning' => 'Estimativa populacional para adultos saudáveis, com grande variação individual. Não usar isoladamente para prescrição, triagem ou avaliação de risco.',
        ];

        if ($age === null) {
            return $result;
        }

        // O uso além deste intervalo exige protocolo individual validado.
        if ($age < 18 || $age > 100) {
            $result['status'] = 'outside_review_scope';
            return $result;
        }

        $estimated = 208.0 - 0.7 * $age;
        if (!is_finite($estimated) || $estimated <= 0) {
            $result['status'] = 'invalid_formula_result';
            return $result;
        }

        $result['status'] = 'preview_available';
        $result['estimated_bpm'] = (int) round($estimated);

        return $result;
    }
}
