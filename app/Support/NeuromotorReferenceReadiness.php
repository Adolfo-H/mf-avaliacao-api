<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Conferência de preenchimento antes da homologação de referências.
 * NÃO calcula classificação, score normativo ou risco clínico.
 */
final class NeuromotorReferenceReadiness
{
    public const VERSION = 'neuromotor-reference-readiness-v1';

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function evaluate(array $payload): array
    {
        $flex = is_array($payload['flexibility'] ?? null) ? $payload['flexibility'] : [];
        $resistance = is_array($payload['resistance'] ?? null) ? $payload['resistance'] : [];

        $items = [
            'flexibility' => $this->checkFlexibility($flex),
            'abdominal_flexion' => $this->checkResistance($resistance['abdominal_flexion'] ?? null),
            'push_ups' => $this->checkResistance($resistance['push_ups'] ?? null),
        ];
        $ready = count(array_filter($items, fn (array $item): bool => $item['status'] === 'recorded_for_review'));

        return [
            'version' => self::VERSION,
            'status' => 'pending_professional_validation',
            'items' => $items,
            'recorded_count' => $ready,
            'total_count' => count($items),
            'summary' => sprintf('%d de %d testes com medidas mínimas registradas para revisão. Nenhuma classificação automática é emitida.', $ready, count($items)),
            'classification' => null,
            'professionally_approved' => false,
        ];
    }

    /** @param array<string, mixed> $test */
    private function checkFlexibility(array $test): array
    {
        $protocol = $test['protocol'] ?? null;
        $value = $test['result'] ?? null;
        $unit = $test['unit'] ?? null;
        if (! in_array($protocol, ['wells_dillon', 'flexitest'], true)) {
            return ['status' => 'missing_protocol'];
        }
        if (! $this->validNumber($value)) {
            return ['status' => 'missing_or_invalid_value'];
        }
        if (! is_string($unit) || trim($unit) === '') {
            return ['status' => 'missing_unit'];
        }
        return ['status' => 'recorded_for_review'];
    }

    private function checkResistance(mixed $test): array
    {
        if (! is_array($test)) {
            return ['status' => 'missing_measurements'];
        }
        $repetitions = $test['repetitions'] ?? null;
        if (! $this->validNumber($repetitions) || (float) $repetitions < 0 || floor((float) $repetitions) !== (float) $repetitions) {
            return ['status' => 'missing_measurements'];
        }
        // Duração/padrão da execução precisa constar no protocolo para comparação válida.
        if (empty($test['protocol']) || empty($test['time_seconds'])) {
            return ['status' => 'missing_protocol_or_duration'];
        }
        return ['status' => 'recorded_for_review'];
    }

    private function validNumber(mixed $value): bool
    {
        return ! is_bool($value) && is_numeric($value) && is_finite((float) $value);
    }
}
