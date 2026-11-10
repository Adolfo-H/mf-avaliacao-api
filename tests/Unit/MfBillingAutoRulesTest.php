<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\MfBillingAutoRules as Rules;
use PHPUnit\Framework\TestCase;

final class MfBillingAutoRulesTest extends TestCase
{
    public function test_deadline_is_after_due_date_and_later_steps_are_spaced(): void
    {
        $this->assertSame('2026-11-13 09:00', Rules::schedule('2026-11-10', 3, 5, 1, '09:00')->format('Y-m-d H:i'));
        $this->assertSame('2026-11-18 09:00', Rules::schedule('2026-11-10', 3, 5, 2, '09:00')->format('Y-m-d H:i'));
        $this->assertSame('2026-11-23 09:00', Rules::schedule('2026-11-10', 3, 5, 3, '09:00')->format('Y-m-d H:i'));
        $this->assertSame('2027-03-03 08:30', Rules::schedule('2027-02-28', 3, 4, 1, '08:30')->format('Y-m-d H:i'));
    }

    public function test_templates_allow_variables_but_reject_unsupported_tokens_and_repetition(): void
    {
        $list = Rules::validateTemplates(Rules::DEFAULT_TEMPLATES, 3);
        $this->assertCount(3, $list);
        $this->assertNotSame($list[0], $list[1]);
        $this->expectException(\InvalidArgumentException::class);
        Rules::validateTemplates([$list[0], $list[0], $list[2]], 2);
    }

    public function test_template_rendering_keeps_required_disclaimer_and_opt_out(): void
    {
        $s = Rules::render('Olá, {nome}! Seu débito da competência {mes} no valor {valor} está pendente.', [
            'nome'=>'Ana', 'mes'=>'11/2026', 'valor'=>'R$ 250,00',
            'data'=>'10/11/2026', 'estudio'=>'MF', 'dias_atraso'=>'3',
        ]);
        $this->assertStringContainsString('Ana', $s);
        $this->assertStringContainsString('R$ 250,00', $s);
        $this->assertStringContainsString('já tenha pago', $s);
        $this->assertStringContainsString('SAIR', $s);
    }
}
