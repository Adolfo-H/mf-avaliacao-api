<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Prévia matemática do teste de Cooper (12 minutos).
 * Não é laudo, classificação ou estimativa laboratorial; nunca é persistida.
 * Referência: Bandyopadhyay A. Biol Sport. 2015;32:59–63.
 * doi:10.5604/20831862.1127283
 */
final class Cooper12MinutePreviewCalculator
{
    public const VERSION = 'cooper-12min-classic-1.0';
    public const FORMULA = '22.351 * (distance_m / 1000) - 11.288';
    public const REFERENCE = 'Bandyopadhyay, Biology of Sport (2015), doi:10.5604/20831862.1127283';

    /** @return array<string, mixed> */
    public function evaluate(?string $protocol, mixed $distanceMeters, mixed $timeSeconds, ?int $age): array
    {
        $data = [
            'protocol' => $protocol,
            'status' => 'protocol_not_selected',
            'formula_version' => self::VERSION,
            'formula' => self::FORMULA,
            'reference' => self::REFERENCE,
            'unit' => 'ml/kg/min',
            'estimated_vo2_ml_kg_min' => null,
            'distance_m_used' => null,
            'professionally_approved' => false,
            'persisted' => false,
            'classification' => null,
            'warning' => 'Estimativa indireta do teste de campo, não é medida laboratorial de VO2 máximo. Aplicabilidade requer homologação profissional.',
        ];

        if ($protocol === null || trim($protocol) === '') {
            return $data;
        }
        if ($protocol !== 'cooper_12_min') {
            return [...$data, 'status' => 'pending_professional_validation'];
        }
        if ($age === null) {
            return [...$data, 'status' => 'missing_age'];
        }
        // O estudo original envolveu adultos. Não estimar para menores.
        if ($age < 18) {
            return [...$data, 'status' => 'outside_review_scope'];
        }
        if ($distanceMeters === null || $distanceMeters === '') {
            return [...$data, 'status' => 'missing_distance'];
        }
        if (!is_numeric($distanceMeters)) {
            return [...$data, 'status' => 'invalid_distance'];
        }
        $distance = (float) $distanceMeters;
        if (!is_finite($distance) || $distance <= 504.9 || $distance > 6000) {
            return [...$data, 'status' => 'invalid_distance'];
        }
        // O formulário também é usado em outros protocolos; em Cooper o
        // tempo pode ficar vazio, pois a duração é fixada em 12 minutos.
        if ($timeSeconds !== null && $timeSeconds !== '') {
            if (!is_numeric($timeSeconds) || (float) $timeSeconds !== 720.0) {
                return [...$data, 'status' => 'invalid_duration'];
            }
        }
        $estimated = 22.351 * ($distance / 1000.0) - 11.288;
        if (!is_finite($estimated) || $estimated <= 0) {
            return [...$data, 'status' => 'invalid_formula_result'];
        }
        return [
            ...$data,
            'status' => 'preview_available',
            'estimated_vo2_ml_kg_min' => round($estimated, 2),
            'distance_m_used' => round($distance, 2),
        ];
    }
}
