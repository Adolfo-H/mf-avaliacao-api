<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\MfBillingAccount;
use App\Models\MfBillingInvoice;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regressão: configuração financeira salva não pode parecer invisível.
 * Não usa WhatsApp real nem altera a base do cliente.
 */
final class MfBillingStartMonthVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mf_billing.enabled' => true,
            'mf_billing_local.enabled' => true,
            'mf_billing_local.worker_token' => str_repeat('X', 48),
            'mf_billing.whatsapp.enabled' => false,
        ]);
        $this->travelTo(CarbonImmutable::parse('2026-11-12 08:20:00', 'America/Sao_Paulo'));
        $admin = User::factory()->create(['role' => UserRole::Admin, 'active' => true]);
        $this->student = Student::create([
            'name' => 'Adolfo - teste de competencia',
            'active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        Sanctum::actingAs($admin);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function configure(array $overrides = []): array
    {
        $data = array_replace([
            'revision' => 0,
            'amount_cents' => 25000,
            'due_day' => 9,
            'reminder_grace_day' => 11,
            'start_month' => '2026-11',
            'enabled' => true,
            'whatsapp_phone' => '+55 45 99987-3195',
            'whatsapp_consent' => true,
            'consent_note' => 'Contato autorizado para este teste automatizado',
        ], $overrides);

        return $this->putJson('/api/v1/billing/students/'.$this->student->uuid, $data)
            ->assertOk()->json('data');
    }

    public function test_salvar_mes_atual_gera_uma_fatura_imediatamente_sem_enviar_aviso(): void
    {
        $account = $this->configure();
        $this->assertSame('2026-11', $account['start_month']);
        $this->assertDatabaseCount('mf_billing_invoices', 1);
        $invoice = MfBillingInvoice::query()->sole();
        $this->assertSame('2026-11-09', $invoice->due_date->format('Y-m-d'));
        $this->assertSame(25000, (int) $invoice->amount_cents);
        $this->getJson('/api/v1/billing/invoices?month=2026-11&status=all')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'overdue')
            ->assertJsonCount(0, 'scheduled_accounts');
        $this->postJson('/api/v1/billing/generate', ['month' => '2026-11'])
            ->assertOk()->assertJsonPath('created', 0);
        $this->assertDatabaseCount('mf_billing_auto_outbox', 0);
        $this->assertDatabaseCount('mf_billing_reminders', 0);
        Http::assertNothingSent();
    }

    public function test_mes_futuro_aparece_como_cadastro_programado_e_nao_gera_fatura_antecipada(): void
    {
        $this->configure(['start_month' => '2026-12']);
        $this->assertDatabaseCount('mf_billing_accounts', 1);
        $this->assertDatabaseCount('mf_billing_invoices', 0);
        $this->getJson('/api/v1/billing/invoices?month=2026-11&status=all')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonCount(1, 'scheduled_accounts')
            ->assertJsonPath('scheduled_accounts.0.student_name', 'Adolfo - teste de competencia')
            ->assertJsonPath('scheduled_accounts.0.start_month', '2026-12')
            ->assertJsonPath('summary.outstanding_cents', 0);

        // Alterar somente o início é permitido enquanto não existem faturas.
        $this->configure(['revision' => 1, 'start_month' => '2026-11']);
        $this->assertDatabaseCount('mf_billing_invoices', 1);
        $this->getJson('/api/v1/billing/invoices?month=2026-11&status=all')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonCount(0, 'scheduled_accounts');
    }

    public function test_reconfigurar_nao_duplica_nem_altera_uma_fatura_existente(): void
    {
        $this->configure();
        $invoice = MfBillingInvoice::query()->sole();
        $this->configure(['revision' => 1, 'due_day' => 10, 'reminder_grace_day' => 11, 'amount_cents' => 35000]);
        $this->assertDatabaseCount('mf_billing_invoices', 1);
        $this->assertSame($invoice->id, MfBillingInvoice::query()->sole()->id);
        $this->assertSame('2026-11-09', MfBillingInvoice::query()->sole()->due_date->format('Y-m-d'));
        $this->assertSame(25000, (int) MfBillingInvoice::query()->sole()->amount_cents);
        $this->assertSame(35000, (int) MfBillingAccount::query()->sole()->amount_cents);
    }

    public function test_conta_desativada_nao_cria_fatura(): void
    {
        $this->configure(['enabled' => false]);
        $this->assertDatabaseCount('mf_billing_invoices', 0);
        $this->getJson('/api/v1/billing/invoices?month=2026-11&status=all')
            ->assertOk()->assertJsonCount(0, 'scheduled_accounts');
    }
}
