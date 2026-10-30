<?php

namespace App\Support;

/**
 * Prévia de regressões por perímetros de Arthur Weltman e colaboradores.
 * Usar exclusivamente para conferência profissional, sem laudo automático.
 *
 * Homens: Weltman, Seip & Tran, Human Biology, 1987, 59(3), 523+.
 * Mulheres: Weltman et al., AJCN 1988, 48(5), 1179-1183.
 */
final class WeltmanObesityPreviewCalculator
{
    public const VERSION = 'weltman-obesity-preview-08F-v1';

    private const REFERENCES = [
        'male' => [
            'title' => 'Weltman, Seip e Tran (1987): adultos com obesidade, amostra masculina',
            'url' => 'https://digitalcommons.wayne.edu/humbiol/vol59/iss3/12/',
            'formula' => '%G = 0.31457 × abdômen médio (cm) − 0.10969 × peso (kg) + 10.8336',
        ],
        'female' => [
            'title' => 'Weltman et al. (1988): mulheres com obesidade',
            'url' => 'https://doi.org/10.1093/ajcn/48.5.1179',
            'formula' => '%G = 0.11077 × abdômen médio (cm) − 0.17666 × altura (cm) + 0.14354 × peso (kg) + 51.03301',
        ],
    ];

    /** @return array<string, mixed> */
    public static function preview(
        bool $protocolSelected,
        ?string $sex,
        ?int $age,
        mixed $weightKg,
        mixed $heightM,
        mixed $upperCm,
        mixed $umbilicusCm,
        bool $applicabilityConfirmed
    ): array {
        $weight = self::number($weightKg);
        $height = self::number($heightM);
        $upper = self::number($upperCm);
        $umbilicus = self::number($umbilicusCm);
        $mean = $upper !== null && $umbilicus !== null && $upper > 0 && $umbilicus > 0
            ? ($upper + $umbilicus) / 2 : null;

        $result = [
            'status' => 'not_calculated',
            'percentage' => null,
            'unit' => '%',
            'calculated' => false,
            'homologated' => false,
            'clinical_result_saved' => false,
            'calculator_version' => self::VERSION,
            'formula' => self::REFERENCES[$sex]['formula'] ?? null,
            'reference' => self::REFERENCES[$sex]['title'] ?? null,
            'reference_url' => self::REFERENCES[$sex]['url'] ?? null,
            'inputs' => [
                'sex' => $sex,
                'age_at_evaluation' => $age,
                'weight_kg' => $weight,
                'height_cm' => $height !== null ? round($height * 100, 2) : null,
                'abdomen_upper_cm' => $upper,
                'abdomen_umbilicus_cm' => $umbilicus,
                'mean_abdomen_cm' => $mean !== null ? round($mean, 2) : null,
                'applicability_confirmed' => $applicabilityConfirmed,
            ],
            'notice' => 'Prévia de uma equação populacional, não homologada; não equivale a diagnóstico nem integra os resultados oficiais.',
        ];

        if (! $protocolSelected) {
            return self::unavailable($result, 'protocol_not_selected', 'Selecione e salve o protocolo Weltman antes de consultar a prévia.');
        }
        if (! $applicabilityConfirmed) {
            return self::unavailable($result, 'applicability_not_confirmed', 'É necessária a confirmação profissional de aplicabilidade da equação para esta pessoa. Não é uma classificação automática de obesidade.');
        }
        if ($sex !== 'male' && $sex !== 'female') {
            return self::unavailable($result, 'sex_not_supported', 'As equações de origem foram desenvolvidas para grupos masculino e feminino. Não há uma equação validada neste módulo para o cadastro informado.');
        }
        if ($age === null || $age < 18) {
            return self::unavailable($result, 'adult_required', 'Esta prévia se destina apenas a adultos. Confirme a data de nascimento.');
        }
        if ($weight === null || $weight <= 0) {
            return self::unavailable($result, 'missing_weight', 'Informe o peso em kg na composição corporal e salve.');
        }
        if ($sex === 'female' && ($height === null || $height <= 0)) {
            return self::unavailable($result, 'missing_height', 'Para a equação feminina, informe a altura em metros na composição corporal e salve.');
        }
        if ($upper === null || $umbilicus === null || $upper <= 0 || $umbilicus <= 0) {
            return self::unavailable($result, 'missing_abdominal_measurements', 'Registre as duas circunferências abdominais em centímetros. Não substituir automaticamente pela cintura.');
        }
        if ($weight > 500 || ($height !== null && $height > 5) || $upper > 300 || $umbilicus > 300) {
            return self::unavailable($result, 'invalid_measurements', 'Os dados estão fora dos limites do formulário. Confira unidades e medidas.');
        }

        $percent = $sex === 'male'
            ? 0.31457 * $mean - 0.10969 * $weight + 10.8336
            : 0.11077 * $mean - 0.17666 * ($height * 100) + 0.14354 * $weight + 51.03301;

        if (! is_finite($percent) || $percent < 0 || $percent > 100) {
            return self::unavailable($result, 'outside_physical_range', 'A equação retornou um percentual fora de 0 a 100%. Confira medidas e aplicabilidade; nenhum valor foi corrigido artificialmente.');
        }

        $result['status'] = 'estimated_unvalidated';
        $result['percentage'] = round($percent, 2);
        $result['calculated'] = true;
        $result['reason'] = null;

        return $result;
    }

    private static function number(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }
        $number = (float) $value;
        return is_finite($number) ? $number : null;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private static function unavailable(array $data, string $status, string $reason): array
    {
        $data['status'] = $status;
        $data['reason'] = $reason;
        return $data;
    }
}
