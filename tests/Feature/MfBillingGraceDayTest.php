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

final class MfBillingGraceDayTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

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
        Http::preventStrayRequests();
        $this->travelTo(CarbonImmutable::parse('2026-11-14 11:00:00', 'America/Sao_Paulo'));
        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'active' => true]);
        $this->student = Student::create([
            'name' => 'Pessoa em teste', 'active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
        Sanctum::actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function payload(?int $graceDay): array
    {
        return [
            'revision' => 0, 'amount_cents' => 25000, 'due_day' => 11,
            'reminder_grace_day' => $graceDay, 'start_month' => '2026-11',
            'enabled' => true, 'whatsapp_phone' => '+55 45 99999-0000',
            'whatsapp_consent' => true, 'consent_note' => 'Autorização em teste',
        ];
    }

    private function accountUrl(): string
    {
        return '/api/v1/billing/students/'.$this->student->uuid;
    }

    private function createInvoice(?int $limit = 14): MfBillingInvoice
    {
        $this->putJson($this->accountUrl(), $this->payload($limit))
            ->assertOk()->assertJsonPath('data.reminder_grace_day', $limit);
        $this->postJson('/api/v1/billing/generate', ['month' => '2026-11'])->assertOk();

        return MfBillingInvoice::query()->sole();
    }

    private function enableAutomation(): void
    {
        $settings = $this->getJson('/api/v1/billing/automation/settings')->assertOk()->json('data');
        $this->putJson('/api/v1/billing/automation/settings', [
            'revision' => $settings['revision'], 'enabled' => true,
            'sender_phone' => '+55 45 99999-1111',
            'first_after_days' => 3, 'repeat_interval_days' => 5,
            'max_reminders' => 1, 'send_time' => '08:00',
            'templates' => MfBillingAutoRules::DEFAULT_TEMPLATES,
        ])->assertOk();
        $this->withHeader('X-MF-Billing-Worker', str_repeat('X', 48));
    }

    private function heartbeat(): void
    {
        $this->postJson('/api/v1/billing/local-worker/heartbeat', [
            'phone' => '5545999991111', 'ready' => true,
        ])->assertOk()->assertJsonPath('data.matches_sender', true);
    }

    private function claim(): mixed
    {
        return $this->postJson('/api/v1/billing/local-worker/claim', [
            'phone' => '5545999991111',
        ])->assertOk()->json('data');
    }

    public function test_limite_individual_persistido_e_removido_sem_duplicar_cobranca(): void
    {
        $invoice = $this->createInvoice(14);
        self::assertSame(14, MfBillingAccount::query()->sole()->reminder_grace_day);
        $payload = $this->payload(null);
        $payload['revision'] = 1;
        $this->putJson($this->accountUrl(), $payload)->assertOk()
            ->assertJsonPath('data.reminder_grace_day', null);
        $this->postJson('/api/v1/billing/generate', ['month' => '2026-11'])
            ->assertOk()->assertJsonPath('created', 0);
        self::assertSame($invoice->id, MfBillingInvoice::query()->sole()->id);
    }

    public function test_limite_anterior_ao_vencimento_e_bloqueado(): void
    {
        $this->createInvoice(null);
        $payload = $this->payload(10);
        $payload['revision'] = 1;
        $this->putJson($this->accountUrl(), $payload)->assertUnprocessable();
    }

    public function test_no_dia_14_nao_envia_e_no_dia_15_pode_reservar(): void
    {
        $this->enableAutomation();
        $this->createInvoice(14);
        $this->heartbeat();
        self::assertNull($this->claim());
        self::assertSame(0, MfBillingAutoOutbox::query()->count());
        $this->travelTo(CarbonImmutable::parse('2026-11-15 08:02:00', 'America/Sao_Paulo'));
        $this->heartbeat();
        $job = $this->claim();
        self::assertIsArray($job);
        self::assertSame(1, $job['step']);
        self::assertSame(1, MfBillingAutoOutbox::query()->count());
        self::assertNull($this->claim());
    }

    public function test_pagamento_registrado_dia_14_impede_lembrete_dia_15(): void
    {
        $this->enableAutomation();
        $invoice = $this->createInvoice(14);
        $this->postJson('/api/v1/billing/invoices/'.$invoice->uuid.'/pay', [
            'revision' => 1, 'paid_on' => '2026-11-14', 'method' => 'pix',
        ])->assertOk();
        $this->travelTo(CarbonImmutable::parse('2026-11-15 08:02:00', 'America/Sao_Paulo'));
        $this->heartbeat();
        self::assertNull($this->claim());
        self::assertSame(0, MfBillingAutoOutbox::query()->count());
    }
}
