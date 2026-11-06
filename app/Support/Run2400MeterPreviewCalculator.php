<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Prévia SOMENTE de pesquisa para 1,5 milha (aprox. 2,4 km).
 * Fórmula ACSM (Guidelines for Exercise Testing and Prescription, 10a ed.):
 * VO2 (ml/kg/min) = 3,5 + 483 / tempo_em_minutos.
 * Não é medida laboratorial, laudo, nem classificação; nunca persiste.
 */
final class Run2400MeterPreviewCalculator
{
    public const VERSION = 'acsm-1_5mile-run-preview-v1';
    public const FORMULA = '3.5 + 483 / (time_seconds / 60)';
    public const REFERENCE = 'ACSM Guidelines for Exercise Testing and Prescription, 10th ed., Field Tests, 1.5-mile run/walk test';

    /** @return array<string, mixed> */
    public function evaluate(?string $protocol, mixed $distanceMeters, mixed $timeSeconds, ?int $age): array
    {
        $result = [
            'protocol' => $protocol,
            'status' => 'protocol_not_selected',
            'formula_version' => self::VERSION,
            'formula' => self::FORMULA,
            'reference' => self::REFERENCE,
            'unit' => 'ml/kg/min',
            'estimated_vo2_ml_kg_min' => null,
            'distance_m_used' => null,
            'time_seconds_used' => null,
            'professionally_approved' => false,
            'persisted' => false,
            'classification' => null,
            'warning' => 'Prévia indireta de campo para conferência profissional; não representa medição laboratorial. Não prescrever exercício, classificar aptidão ou gerar laudo a partir desta prévia.',
        ];

        if ($protocol === null || $protocol === '') {
            return $result;
        }
        if ($protocol !== 'run_2400m') {
            return [...$result, 'status' => 'other_protocol'];
        }
        if ($age === null) {
            return [...$result, 'status' => 'missing_age'];
        }
        if ($age < 18 || $age > 100) {
            return [...$result, 'status' => 'outside_review_scope'];
        }
        if ($distanceMeters === null || $distanceMeters === '') {
            return [...$result, 'status' => 'missing_distance'];
        }
        if (is_bool($distanceMeters) || ! is_numeric($distanceMeters)) {
            return [...$result, 'status' => 'invalid_distance'];
        }
        $distance = (float) $distanceMeters;
        if (! is_finite($distance)) {
            return [...$result, 'status' => 'invalid_distance'];
        }
        // Aceita 2.400 m ou 1,5 milha internacional (2.414,016 m),
        // com tolerância apenas de anotação. Não confundir trajetos distintos.
        $is2400m = $distance >= 2398 && $distance <= 2402;
        $isOneAndHalfMile = $distance >= 2412 && $distance <= 2416;
        if (! $is2400m && ! $isOneAndHalfMile) {
            return [...$result, 'status' => 'invalid_distance'];
        }
        if ($timeSeconds === null || $timeSeconds === '') {
            return [...$result, 'status' => 'missing_time'];
        }
        if (is_bool($timeSeconds) || ! is_numeric($timeSeconds)) {
            return [...$result, 'status' => 'invalid_time'];
        }
        $seconds = (float) $timeSeconds;
        // Intervalo conservador para evitar resultados espúrios.
        if (! is_finite($seconds) || floor($seconds) !== $seconds || $seconds < 360 || $seconds > 2400) {
            return [...$result, 'status' => 'invalid_time'];
        }

        $estimated = 3.5 + 483.0 / ($seconds / 60.0);
        if (! is_finite($estimated) || $estimated < 3.5 || $estimated > 100) {
            return [...$result, 'status' => 'invalid_formula_result'];
        }

        return [
            ...$result,
            'status' => 'preview_available',
            'estimated_vo2_ml_kg_min' => round($estimated, 2),
            'distance_m_used' => round($distance, 2),
            'time_seconds_used' => (int) $seconds,
        ];
    }
}
