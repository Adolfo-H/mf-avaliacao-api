<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Prévia exclusivamente matemática do método Guedes de três dobras.
 * Coeficientes da equação de densidade corporal; conversão de Siri (1961).
 * O cálculo NÃO está homologado, NÃO persiste e NÃO deve ser usado para laudos.
 *
 * Homens: TR + SI + AB; mulheres: SE + SI + CX, espessuras em milímetros.
 * Idades conservadoras para conferência: homens 18–27, mulheres 18–29.
 */
final class GuedesThreeSkinfoldPreviewCalculator
{
    public const VERSION = 'guedes-1985-siri-1961-preview-v1';

    private const CONFIG = [
        'male' => [
            'minimum_age' => 18,
            'maximum_age' => 27,
            'constant' => 1.17136,
            'log_coefficient' => 0.06706,
            'sites' => ['triceps', 'suprailiac', 'abdominal'],
        ],
        'female' => [
            'minimum_age' => 18,
            'maximum_age' => 29,
            'constant' => 1.16650,
            'log_coefficient' => 0.07063,
            'sites' => ['subscapular', 'suprailiac', 'thigh'],
        ],
    ];

    public function evaluate(?string $protocol, ?string $sex, ?int $age, array $skinfolds): array
    {
        $result = [
            'status' => 'protocol_not_selected',
            'message' => 'Selecione Guedes de 3 dobras e salve a composição corporal.',
            'protocol' => $protocol,
            'protocol_label' => 'Guedes de 3 dobras',
            'formula_version' => self::VERSION,
            'professionally_approved' => false,
            'persisted' => false,
            'sex' => $sex,
            'age_at_evaluation' => $age,
            'studied_age_range' => null,
            'required_skinfolds' => [],
            'missing_skinfolds' => [],
            'sum_skinfolds_mm' => null,
            'body_density_g_ml' => null,
            'body_fat_percentage_preview' => null,
            'formula' => null,
            'conversion' => 'Siri (1961): ((4.95 / densidade) - 4.50) × 100',
            'reference' => 'Guedes, D.P. (1985); conversão de Siri (1961). Revisar população, equação e procedimentos antes de homologar.',
        ];

        if ($protocol !== 'guedes_3') {
            return $result;
        }

        $result['status'] = 'unsupported_sex_for_equation';
        $result['message'] = 'Esta equação apresenta parâmetros masculino/feminino; não extrapolar a outras categorias sem protocolo aprovado.';
        if (! isset(self::CONFIG[$sex ?? ''])) {
            return $result;
        }

        $config = self::CONFIG[$sex];
        $result['required_skinfolds'] = $config['sites'];
        $result['studied_age_range'] = [
            'min' => $config['minimum_age'],
            'max' => $config['maximum_age'],
        ];
        $result['formula'] = sprintf(
            'D = %.5f - %.5f × log10(soma de 3 dobras em mm)',
            $config['constant'],
            $config['log_coefficient'],
        );

        if ($age === null) {
            $result['status'] = 'missing_age';
            $result['message'] = 'É necessária a data de nascimento para verificar a população estudada.';
            return $result;
        }

        if ($age < $config['minimum_age'] || $age > $config['maximum_age']) {
            $result['status'] = 'outside_studied_age_range';
            $result['message'] = 'Idade fora da faixa conservadora escolhida para conferência deste protocolo.';
            return $result;
        }

        $values = [];
        $missing = [];
        foreach ($config['sites'] as $key) {
            $value = $skinfolds[$key] ?? null;
            if ($value === null || $value === '') {
                $missing[] = $key;
                continue;
            }
            if (! is_numeric($value) || ! is_finite((float) $value)
                || (float) $value <= 0 || (float) $value > 999) {
                $result['status'] = 'invalid_measurements';
                $result['message'] = 'Revise dobras com valores não numéricos, nulos ou fisicamente inválidos.';
                return $result;
            }
            $values[] = (float) $value;
        }

        if ($missing !== []) {
            $result['status'] = 'missing_measurements';
            $result['message'] = 'Salve as três dobras obrigatórias para a referência selecionada.';
            $result['missing_skinfolds'] = $missing;
            return $result;
        }

        $sum = array_sum($values);
        $density = $config['constant'] - $config['log_coefficient'] * log10($sum);
        $fat = $density > 0 ? ((4.95 / $density) - 4.50) * 100 : NAN;
        if (! is_finite($density) || $density <= 0 || ! is_finite($fat)
            || $fat < 0 || $fat > 100) {
            $result['status'] = 'invalid_formula_result';
            $result['message'] = 'Resultado matemático fora dos limites utilizáveis. Conferir as medidas e o protocolo.';
            return $result;
        }

        $result['status'] = 'preview_available';
        $result['message'] = 'Prévia matemática disponível exclusivamente para revisão profissional, sem homologação.';
        $result['sum_skinfolds_mm'] = round($sum, 2);
        $result['body_density_g_ml'] = round($density, 5);
        $result['body_fat_percentage_preview'] = round($fat, 2);
        return $result;
    }
}
