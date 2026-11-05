<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Prévia de pesquisa: Jackson-Pollock (3 e 7 dobras), convertido por Siri.
 * Nunca grava resultado, classifica risco ou substitui validação profissional.
 * Equações de densidade e sítios: https://www.jcdr.net/ReadXMLFile.aspx?id=12975
 * Descrição comparativa: https://pmc.ncbi.nlm.nih.gov/articles/PMC11374165/
 */
final class ClinicalSkinfoldPreviewCalculator
{
    public const VERSION = 'jp-siri-preview-1.0.0';

    private const SITES_7 = [
        'chest', 'midaxillary', 'triceps', 'subscapular',
        'abdominal', 'suprailiac', 'thigh',
    ];

    private const SITES_3 = [
        'male' => ['chest', 'abdominal', 'thigh'],
        'female' => ['triceps', 'suprailiac', 'thigh'],
    ];

    // Densidade = a - b*S + c*S² - d*idade, com S em milímetros.
    // Referências: Jackson & Pollock 1978 (homens); Jackson et al. 1980 (mulheres).
    private const EQUATIONS = [
        'pollock_7' => [
            'male' => ['a' => 1.112, 'b' => 0.00043499, 'c' => 0.00000055, 'd' => 0.00028826],
            'female' => ['a' => 1.097, 'b' => 0.00046971, 'c' => 0.00000056, 'd' => 0.00012828],
        ],
        'pollock_3' => [
            'male' => ['a' => 1.10938, 'b' => 0.0008267, 'c' => 0.0000016, 'd' => 0.0002574],
            'female' => ['a' => 1.0994921, 'b' => 0.0009929, 'c' => 0.0000023, 'd' => 0.0001392],
        ],
    ];

    private const AGE_BOUNDS = [
        'male' => [18, 61],
        'female' => [18, 55],
    ];

    /** @param array<string, mixed> $skinfolds */
    public function evaluate(?string $protocol, ?string $sex, ?int $age, array $skinfolds): array
    {
        $output = [
            'protocol' => $protocol,
            'protocol_label' => match ($protocol) {
                'pollock_7' => 'Jackson–Pollock de 7 dobras',
                'pollock_3' => 'Jackson–Pollock de 3 dobras',
                'guedes_3' => 'Guedes de 3 dobras',
                'bioimpedance' => 'Bioimpedância',
                'weltman' => 'Weltman',
                default => 'Protocolo não selecionado',
            },
            'status' => 'protocol_not_selected',
            'formula_version' => self::VERSION,
            'age_at_evaluation' => $age,
            'sex_for_equation' => $sex,
            'required_skinfolds' => [],
            'missing_skinfolds' => [],
            'body_density_preview' => null,
            'body_fat_percentage_preview' => null,
            'classification' => null,
            'professionally_approved' => false,
            'persisted' => false,
            'warning' => 'SIMULAÇÃO PARA CONFERÊNCIA PROFISSIONAL. Não é laudo, não substitui avaliação clínica e não é salva na avaliação.',
            'reference' => 'Jackson & Pollock (1978); Jackson, Pollock & Ward (1980); Siri (1961).',
        ];

        if ($protocol === null || $protocol === '') {
            return $output;
        }

        if (! array_key_exists($protocol, self::EQUATIONS)) {
            $output['status'] = 'pending_professional_validation';
            return $output;
        }

        if (! in_array($sex, ['male', 'female'], true)) {
            $output['status'] = 'unsupported_sex_for_equation';
            return $output;
        }

        $sites = $protocol === 'pollock_7' ? self::SITES_7 : self::SITES_3[$sex];
        $output['required_skinfolds'] = $sites;

        if ($age === null) {
            $output['status'] = 'missing_age';
            return $output;
        }

        [$minAge, $maxAge] = self::AGE_BOUNDS[$sex];
        if ($age < $minAge || $age > $maxAge) {
            $output['status'] = 'outside_studied_age_range';
            return $output;
        }

        $missing = [];
        $invalid = [];
        $sum = 0.0;
        foreach ($sites as $site) {
            $value = $skinfolds[$site] ?? null;
            if ($value === null || $value === '') {
                $missing[] = $site;
                continue;
            }
            if (is_bool($value) || ! is_numeric($value)) {
                $invalid[] = $site;
                continue;
            }
            $numeric = (float) $value;
            if (! is_finite($numeric) || $numeric <= 0) {
                $invalid[] = $site;
                continue;
            }
            $sum += $numeric;
        }

        if ($invalid !== []) {
            $output['status'] = 'invalid_measurements';
            $output['missing_skinfolds'] = $invalid;
            return $output;
        }

        if ($missing !== []) {
            $output['status'] = 'missing_measurements';
            $output['missing_skinfolds'] = $missing;
            return $output;
        }

        $e = self::EQUATIONS[$protocol][$sex];
        $density = $e['a'] - $e['b'] * $sum + $e['c'] * $sum * $sum - $e['d'] * $age;
        if (! is_finite($density) || $density <= 0) {
            $output['status'] = 'invalid_formula_result';
            return $output;
        }

        // Conversão de Siri: percentual = (495 / densidade) - 450.
        $fat = 495 / $density - 450;
        if (! is_finite($fat) || $fat < 0 || $fat > 100) {
            $output['status'] = 'invalid_formula_result';
            return $output;
        }

        $output['status'] = 'preview_available';
        $output['body_density_preview'] = round($density, 6);
        $output['body_fat_percentage_preview'] = round($fat, 2);

        return $output;
    }
}
