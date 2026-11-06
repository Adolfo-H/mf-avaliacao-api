<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Comparação de IMC com faixas de referência para ADULTOS da OMS.
 * Apenas prévia matemática/informativa. Não altera resultados oficiais nem PDF.
 * Não utilizar estas faixas para crianças/adolescentes (IMC por idade/sexo).
 * https://www.who.int/europe/news-room/fact-sheets/item/nutrition---maintaining-a-healthy-lifestyle
 */
final class AdultBmiReferencePreviewCalculator
{
    public const VERSION = 'who-adult-bmi-preview-v1';

    /** @return array<string, mixed> */
    public function evaluate(?int $age, mixed $weightKg, mixed $heightMeters): array
    {
        $result = [
            'status' => 'missing_measurements',
            'formula_version' => self::VERSION,
            'bmi_kg_m2' => null,
            'reference_category' => null,
            'reference' => 'OMS: faixas de referência do IMC em adultos',
            'reference_url' => 'https://www.who.int/europe/news-room/fact-sheets/item/nutrition---maintaining-a-healthy-lifestyle',
            'professionally_approved' => false,
            'persisted' => false,
            'warning' => 'Prévia informativa. IMC não determina sozinho composição corporal, risco individual ou diagnóstico.',
        ];

        if ($age === null) {
            $result['status'] = 'missing_age';
            return $result;
        }

        if ($age < 18) {
            $result['status'] = 'outside_adult_scope';
            return $result;
        }

        if ($weightKg === null || $weightKg === '' || $heightMeters === null || $heightMeters === '') {
            return $result;
        }

        if (is_bool($weightKg) || is_bool($heightMeters) || !is_numeric($weightKg) || !is_numeric($heightMeters)) {
            $result['status'] = 'invalid_measurements';
            return $result;
        }

        $kg = (float) $weightKg;
        $m = (float) $heightMeters;
        if (!is_finite($kg) || !is_finite($m) || $kg <= 0 || $m <= 0) {
            $result['status'] = 'invalid_measurements';
            return $result;
        }

        $bmi = $kg / ($m * $m);
        if (!is_finite($bmi) || $bmi <= 0 || $bmi > 100) {
            $result['status'] = 'invalid_formula_result';
            return $result;
        }

        // Classificar pelo valor bruto, antes de arredondar a exibição.
        $category = match (true) {
            $bmi < 18.5 => 'Baixo peso',
            $bmi < 25.0 => 'Faixa de referência habitual',
            $bmi < 30.0 => 'Sobrepeso',
            $bmi < 35.0 => 'Obesidade classe I',
            $bmi < 40.0 => 'Obesidade classe II',
            default => 'Obesidade classe III',
        };

        $result['status'] = 'preview_available';
        $result['bmi_kg_m2'] = round($bmi, 2);
        $result['reference_category'] = $category;

        return $result;
    }
}
