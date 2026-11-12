<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\MfBillingAccount;
use App\Models\MfBillingAutoOutbox;
use App\Models\MfBillingInvoice;
use App\Models\Student;
use App\Models\User;
use App\Support\MfBillingAutoRules;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class MfBillingIndividualMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mf_billing.enabled' => true,
            'mf_billing_local.enabled' => true,
            'mf_billing_local.worker_token' => str_repeat('A', 64),
            'mf_billing.whatsapp.enabled' => false,
        ]);
        $this->travelTo(CarbonImmutable::parse('2026-11-11 10:00:00', 'America/Sao_Paulo'));
        $user = User::factory()->create(['role' => UserRole::Admin, 'active' => true]);
        Sanctum::actingAs($user);
        $student = Student::create([
            'name' => 'Aluno de Homologação', 'active' => true,
            'created_by' => $user->id, 'updated_by' => $user->id,
        ]);
        $this->student = $student;
        Http::preventStrayRequests();
    }

    private Student $student;

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function prepare(int $max = 2, int $interval = 2): MfBillingInvoice
    {
        $settings = $this->getJson('/api/v1/billing/automation/settings')->assertOk()->json('data');
        $this->putJson('/api/v1/billing/automation/settings', [
            'revision' => $settings['revision'],
            'enabled' => true, 'sender_phone' => '+55 45 99999-1111',
            'first_after_days' => 3, 'repeat_interval_days' => 5,
            'max_reminders' => 1, 'send_time' => '08:00',
            'templates' => MfBillingAutoRules::DEFAULT_TEMPLATES,
        ])->assertOk();
        $this->putJson('/api/v1/billing/students/'.$this->student->uuid, [
            'revision' => 0, 'amount_cents' => 25000,
            'due_day' => 9, 'reminder_grace_day' => 11,
            'max_reminders' => $max, 'reminder_interval_days' => $interval,
            'start_month' => '2026-11', 'enabled' => true,
            'whatsapp_phone' => '+55 45 99999-0000',
            'whatsapp_consent' => true, 'consent_note' => 'Teste autorizado',
        ])->assertOk()
            ->assertJsonPath('data.max_reminders', $max)
            ->assertJsonPath('data.reminder_interval_days', $interval);
        return MfBillingInvoice::query()->sole();
    }

    private function claim(): mixed
    {
        $this->withHeader('X-MF-Billing-Worker', str_repeat('A', 64));
        $this->postJson('/api/v1/billing/local-worker/heartbeat', [
            'phone' => '5545999991111', 'ready' => true,
        ])->assertOk();
        return $this->postJson('/api/v1/billing/local-worker/claim', [
            'phone' => '5545999991111',
        ])->assertOk()->json('data');
    }

    public function test_prazo_por_aluno_e_modelos_aleatorios_sem_repeticao(): void
    {
        $this->prepare(2, 2);
        $this->assertSame(2, (int) MfBillingAccount::query()->sole()->max_reminders);
        self::assertNull($this->claim()); // Dia 11 ainda pode pagar.

        $this->travelTo(CarbonImmutable::parse('2026-11-12 08:07:00', 'America/Sao_Paulo'));
        $first = $this->claim();
        self::assertSame(1, $first['step']);
        $firstModel = MfBillingAutoOutbox::query()->sole()->template_index;
        self::assertContains($firstModel, [0, 1, 2]);
        $this->postJson('/api/v1/billing/local-worker/jobs/'.$first['uuid'].'/check', [
            'phone' => '5545999991111',
        ])->assertOk()->assertJsonPath('data.can_send', true);
        $this->postJson('/api/v1/billing/local-worker/jobs/'.$first['uuid'].'/ack', [
            'status' => 'sent', 'provider_id' => 'teste-homologacao-primeiro',
        ])->assertOk()->assertJsonPath('data.status', 'sent');
        self::assertNull($this->claim());

        $this->travelTo(CarbonImmutable::parse('2026-11-14 08:07:00', 'America/Sao_Paulo'));
        $second = $this->claim();
        self::assertSame(2, $second['step']);
        self::assertNotSame($first['message'], $second['message']);
        $secondModel = MfBillingAutoOutbox::query()->where('step', 2)->sole()->template_index;
        self::assertNotSame($firstModel, $secondModel);
        $this->postJson('/api/v1/billing/local-worker/jobs/'.$second['uuid'].'/check', [
            'phone' => '5545999991111',
        ])->assertOk()->assertJsonPath('data.can_send', true);
        $this->postJson('/api/v1/billing/local-worker/jobs/'.$second['uuid'].'/ack', [
            'status' => 'sent', 'provider_id' => 'teste-homologacao-segundo',
        ])->assertOk();

        $this->travelTo(CarbonImmutable::parse('2026-11-16 08:07:00', 'America/Sao_Paulo'));
        self::assertNull($this->claim()); // Limite individual de dois avisos, independentemente do global.
        self::assertSame(2, MfBillingAutoOutbox::query()->count());
        Http::assertNothingSent(); // Nenhuma chamada ao provedor real.
    }

    public function test_pagamento_bloqueia_segundo_aviso(): void
    {
        $invoice = $this->prepare(3, 2);
        $this->travelTo(CarbonImmutable::parse('2026-11-12 08:07:00', 'America/Sao_Paulo'));
        $first = $this->claim();
        $this->postJson('/api/v1/billing/local-worker/jobs/'.$first['uuid'].'/check', [
            'phone' => '5545999991111',
        ])->assertOk();
        $this->postJson('/api/v1/billing/local-worker/jobs/'.$first['uuid'].'/ack', [
            'status' => 'sent', 'provider_id' => 'teste-pagamento',
        ])->assertOk();
        $this->postJson('/api/v1/billing/invoices/'.$invoice->uuid.'/pay', [
            'revision' => $invoice->revision,
            'paid_on' => '2026-11-12', 'method' => 'pix',
        ])->assertOk();
        $this->travelTo(CarbonImmutable::parse('2026-11-14 08:07:00', 'America/Sao_Paulo'));
        self::assertNull($this->claim());
        self::assertSame(1, MfBillingAutoOutbox::query()->count());
    }
}
