<?php

namespace App\Support;

use App\Models\Assessment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

/**
 * Auditoria clínica: trilha de campos alterados sem expor conteúdo
 * clínico em texto aberto no banco de auditoria.
 */
final class ClinicalAuditLogger
{
    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    public static function record(
        Request $request,
        Assessment $assessment,
        string $section,
        array $before,
        array $after
    ): void {
        $changes = self::diff($before, $after);

        // Não gera log novo em salvamento sem alteração.
        if ($changes === []) {
            return;
        }

        AuditLogger::record(
            $request,
            'assessment.clinical.update',
            subject: $assessment,
            assessmentId: $assessment->id,
            studentId: $assessment->student_id,
            metadata: [
                'section' => $section,
                'changed_fields' => array_keys($changes),
                'format' => 'encrypted-v1',
                'changes_encrypted' => Crypt::encryptString(
                    json_encode(
                        $changes,
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
                    )
                ),
            ],
        );
    }

    /**
     * @param array<array-key, mixed> $before
     * @param array<array-key, mixed> $after
     * @return array<string, array<string, mixed>>
     */
    private static function diff(
        array $before,
        array $after,
        string $prefix = ''
    ): array {
        $changes = [];

        foreach (
            array_unique(array_merge(array_keys($before), array_keys($after)))
            as $key
        ) {
            $name = $prefix === ''
                ? (string) $key
                : $prefix.'.'.$key;

            $hadBefore = array_key_exists($key, $before);
            $hasAfter = array_key_exists($key, $after);
            $old = $hadBefore ? $before[$key] : null;
            $new = $hasAfter ? $after[$key] : null;

            if (
                $hadBefore &&
                $hasAfter &&
                is_array($old) &&
                is_array($new) &&
                ! array_is_list($old) &&
                ! array_is_list($new)
            ) {
                $changes += self::diff($old, $new, $name);
                continue;
            }

            if (
                $hadBefore === $hasAfter &&
                (
                    $old === $new ||
                    (
                        is_numeric($old) &&
                        is_numeric($new) &&
                        (float) $old === (float) $new
                    )
                )
            ) {
                continue;
            }

            $changes[$name] = [
                'old_present' => $hadBefore,
                'old' => $old,
                'new_present' => $hasAfter,
                'new' => $new,
            ];
        }

        return $changes;
    }
}
