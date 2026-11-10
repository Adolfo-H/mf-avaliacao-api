<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\MfBillingAutoOutbox;
use App\Models\Student;
use App\Models\User;
use App\Support\MfBillingAutoRules;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class MfBillingBrazilianSenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mf_billing.enabled' => true,
            'mf_billing_local.enabled' => true,
            'mf_billing_local.worker_token' => str_repeat('X', 48),
            'mf_billing.whatsapp.enabled' => false,
        ]);
        $this->travelTo(CarbonImmutable::parse('2026-11-13 13:00:00', 'America/Sao_Paulo'));
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Admin, 'active' => true]));
        $this->withHeader('X-MF-Billing-Worker', str_repeat('X', 48));
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function configure(): void
    {
        $s = $this->getJson('/api/v1/billing/automation/settings')->assertOk()->json('data');
        $this->putJson('/api/v1/billing/automation/settings', [
            'revision' => $s['revision'],
            'enabled' => true,
            'sender_phone' => '+55 11 99876-5432',
            'first_after_days' => 3,
            'repeat_interval_days' => 5,
            'max_reminders' => 1,
            'send_time' => '09:00',
            'templates' => MfBillingAutoRules::DEFAULT_TEMPLATES,
        ])->assertOk();
    }

    public function test_worker_without_ninth_digit_can_heartbeat_claim_and_check(): void
    {
        $this->configure();
        $admin = User::query()->where('role', UserRole::Admin->value)->firstOrFail();
        $student = Student::create([
            'name' => 'Aluno de Teste',
            'active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        $this->putJson('/api/v1/billing/students/'.$student->uuid, [
            'revision' => 0,
            'amount_cents' => 25000,
            'due_day' => 10,
            'start_month' => '2026-11',
            'enabled' => true,
            'whatsapp_phone' => '+55 45 99999-0000',
            'whatsapp_consent' => true,
            'consent_note' => 'Autorizacao de teste',
        ])->assertOk();
        $this->postJson('/api/v1/billing/generate', ['month' => '2026-11'])->assertOk();

        $phone = '551198765432';
        $this->postJson('/api/v1/billing/local-worker/heartbeat', [
            'phone' => $phone,
            'ready' => true,
        ])->assertOk()->assertJsonPath('data.matches_sender', true);

        $this->getJson('/api/v1/billing/automation/settings')
            ->assertOk()->assertJsonPath('data.worker_matches_sender', true);

        $job = $this->postJson('/api/v1/billing/local-worker/claim', ['phone' => $phone])
            ->assertOk()->json('data');
        $this->assertNotNull($job);

        $this->postJson('/api/v1/billing/local-worker/jobs/'.$job['uuid'].'/check', ['phone' => $phone])
            ->assertOk()->assertJsonPath('data.can_send', true);
        $this->assertDatabaseCount('mf_billing_auto_outbox', 1);
    }

    public function test_different_ddd_is_rejected_and_cannot_reserve_job(): void
    {
        $this->configure();
        $phone = '552198765432';
        $this->postJson('/api/v1/billing/local-worker/heartbeat', [
            'phone' => $phone,
            'ready' => true,
        ])->assertOk()->assertJsonPath('data.matches_sender', false);
        $this->postJson('/api/v1/billing/local-worker/claim', ['phone' => $phone])
            ->assertOk()->assertJsonPath('data', null);
        $this->assertSame(0, MfBillingAutoOutbox::query()->count());
    }
}
