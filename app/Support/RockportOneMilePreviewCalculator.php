<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Prévia NÃO HOMOLOGADA da equação generalizada de Kline et al. (1987).
 * Somente adultos 30–69 anos (população original), marcha de UMA milha em pista.
 * Ref.: PMID 3600239. Resultado não deve ser persistido em fichas clínicas.
 */
final class RockportOneMilePreviewCalculator
{
    public const VERSION = 'kline-rockport-1987-preview-v1';
    public const REFERENCE = 'Kline GM et al. Med Sci Sports Exerc. 1987;19(3):253-259. PMID: 3600239.';
    public const FORMULA = '132.853 - 0.0769 × peso_lb - 0.3877 × idade + 6.315 × sexo_masculino - 3.2649 × tempo_min - 0.1565 × FC_final';

    public function evaluate(
        ?string $protocol,
        mixed $distanceMeters,
        mixed $timeSeconds,
        mixed $heartRateBpm,
        mixed $weightKg,
        ?int $ageYears,
        ?string $sex
    ): array {
        $preview = [
            'protocol' => $protocol,
            'status' => 'protocol_not_selected',
            'formula_version' => self::VERSION,
            'formula' => self::FORMULA,
            'reference' => self::REFERENCE,
            'reference_url' => 'https://pubmed.ncbi.nlm.nih.gov/3600239/',
            'unit' => 'ml/kg/min',
            'estimated_vo2_ml_kg_min' => null,
            'distance_m_used' => null,
            'time_seconds_used' => null,
            'heart_rate_bpm_used' => null,
            'weight_kg_used' => null,
            'age_years_used' => null,
            'sex_used' => null,
            'professionally_approved' => false,
            'persisted' => false,
            'classification' => null,
            'warning' => 'Estimativa indireta, não homologada para laudo. Equação original estudada em adultos saudáveis de 30 a 69 anos. Exige caminhada de 1 milha em pista, sem corrida, e FC imediatamente ao término. Não substitui ergoespirometria nem avaliação profissional.',
        ];

        if ($protocol === null || $protocol === '') {
            return $preview;
        }
        if ($protocol !== 'walk_1_mile') {
            $preview['status'] = 'pending_professional_validation';
            return $preview;
        }

        if ($ageYears === null) {
            $preview['status'] = 'missing_age';
            return $preview;
        }
        if ($ageYears < 30 || $ageYears > 69) {
            $preview['status'] = 'outside_original_age_range';
            return $preview;
        }
        if ($sex !== 'male' && $sex !== 'female') {
            $preview['status'] = 'sex_not_supported_by_equation';
            return $preview;
        }

        if ($distanceMeters === null || $distanceMeters === '') {
            $preview['status'] = 'missing_distance';
            return $preview;
        }
        $distance = $this->number($distanceMeters);
        // Uma milha internacional corresponde a 1609,344 metros; tolerância de anotação de 1 metro.
        if ($distance === null || $distance < 1608.0 || $distance > 1611.0) {
            $preview['status'] = 'invalid_mile_distance';
            return $preview;
        }

        if ($timeSeconds === null || $timeSeconds === '') {
            $preview['status'] = 'missing_time';
            return $preview;
        }
        $seconds = $this->number($timeSeconds);
        if ($seconds === null || $seconds < 480 || $seconds > 2400 || floor($seconds) !== $seconds) {
            $preview['status'] = 'invalid_time';
            return $preview;
        }

        if ($weightKg === null || $weightKg === '') {
            $preview['status'] = 'missing_weight';
            return $preview;
        }
        $kg = $this->number($weightKg);
        if ($kg === null || $kg < 30 || $kg > 300) {
            $preview['status'] = 'invalid_weight';
            return $preview;
        }

        if ($heartRateBpm === null || $heartRateBpm === '') {
            $preview['status'] = 'missing_heart_rate';
            return $preview;
        }
        $hr = $this->number($heartRateBpm);
        if ($hr === null || $hr < 40 || $hr > 220 || floor($hr) !== $hr) {
            $preview['status'] = 'invalid_heart_rate';
            return $preview;
        }

        // A fórmula original usa LIBRAS (não kg) e minutos decimais (não segundos).
        $weightLb = $kg * 2.2046226218488;
        $timeMin = $seconds / 60;
        $maleCoefficient = $sex === 'male' ? 1 : 0;
        $estimate = 132.853
            - 0.0769 * $weightLb
            - 0.3877 * $ageYears
            + 6.315 * $maleCoefficient
            - 3.2649 * $timeMin
            - 0.1565 * $hr;

        if (! is_finite($estimate) || $estimate <= 0 || $estimate > 100) {
            $preview['status'] = 'invalid_formula_result';
            return $preview;
        }

        $preview['status'] = 'preview_available';
        $preview['estimated_vo2_ml_kg_min'] = round($estimate, 2);
        $preview['distance_m_used'] = round($distance, 2);
        $preview['time_seconds_used'] = (int) $seconds;
        $preview['heart_rate_bpm_used'] = (int) $hr;
        $preview['weight_kg_used'] = round($kg, 3);
        $preview['age_years_used'] = $ageYears;
        $preview['sex_used'] = $sex;
        return $preview;
    }

    private function number(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }
        $number = (float) $value;
        return is_finite($number) ? $number : null;
    }
}
