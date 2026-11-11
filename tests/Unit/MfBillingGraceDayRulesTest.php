<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\MfBillingAutoRules;
use PHPUnit\Framework\TestCase;

final class MfBillingGraceDayRulesTest extends TestCase
{
    public function test_vencimento_11_limite_14_primeiro_aviso_15_8h(): void
    {
        $at = MfBillingAutoRules::firstReminderAt('2026-11-11', '2026-11', 14, 3, '08:00');
        self::assertSame('2026-11-15 08:00', $at->format('Y-m-d H:i'));
    }

    public function test_sem_excecao_mantem_o_padrao_global(): void
    {
        $at = MfBillingAutoRules::firstReminderAt('2026-11-11', '2026-11', null, 3, '09:00');
        self::assertSame('2026-11-14 09:00', $at->format('Y-m-d H:i'));
    }

    public function test_dia_31_em_fevereiro_usa_ultimo_dia(): void
    {
        $at = MfBillingAutoRules::firstReminderAt('2027-02-26', '2027-02', 31, 3, '08:00');
        self::assertSame('2027-03-01 08:00', $at->format('Y-m-d H:i'));
    }

    public function test_vencimento_ajustado_nunca_gera_aviso_antecipado(): void
    {
        $at = MfBillingAutoRules::firstReminderAt('2026-11-20', '2026-11', 14, 3, '08:00');
        self::assertSame('2026-11-21 08:00', $at->format('Y-m-d H:i'));
    }
}
