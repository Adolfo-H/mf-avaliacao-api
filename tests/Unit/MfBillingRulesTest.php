<?php
declare(strict_types=1);
namespace Tests\Unit;
use App\Support\MfBillingRules as R;
use PHPUnit\Framework\TestCase;
final class MfBillingRulesTest extends TestCase {
    public function test_end_of_month_and_leap_year(): void {
        $this->assertSame('2026-02-28', R::dueDate('2026-02',31));
        $this->assertSame('2028-02-29', R::dueDate('2028-02',31));
        $this->assertSame('2026-04-30', R::dueDate('2026-04',31));
        $this->assertSame('2026-12-10', R::dueDate('2026-12',10));
    }
    public function test_no_overdue_on_the_due_day(): void {
        $this->assertSame('open',R::status('2026-11-10',false,false,'2026-11-09'));
        $this->assertSame('due_today',R::status('2026-11-10',false,false,'2026-11-10'));
        $this->assertSame('overdue',R::status('2026-11-10',false,false,'2026-11-11'));
        $this->assertSame('paid',R::status('2026-11-10',true,false,'2026-12-01'));
        $this->assertSame('cancelled',R::status('2026-11-10',false,true,'2026-12-01'));
    }
    public function test_money_phone_and_non_accusatory_message(): void {
        $this->assertSame('R$ 250,05',R::money(25005));
        $this->assertSame('5545999990000',R::phone('+55 (45) 99999-0000'));
        $this->assertNull(R::phone(''));
        $message=R::message('Helena Duarte','2026-11',25000,'2026-11-10','MF Saúde & Movimento');
        $this->assertStringContainsString('Caso já tenha pago',$message);
        $this->assertStringContainsString('SAIR',$message);
        $this->assertStringNotContainsString('avaliação clínica',$message);
    }
    public function test_invalid_month_is_rejected(): void { $this->expectException(\InvalidArgumentException::class); R::dueDate('2026-13',10); }
    public function test_invalid_day_is_rejected(): void { $this->expectException(\InvalidArgumentException::class); R::dueDate('2026-01',0); }
    public function test_invalid_phone_is_rejected(): void { $this->expectException(\InvalidArgumentException::class); R::phone('telefone'); }
}
