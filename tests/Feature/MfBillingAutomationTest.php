<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{MfBillingAccount, MfBillingAutoOutbox, MfBillingAutoSetting, MfBillingInvoice, Student, User};
use App\Support\MfBillingAutoRules;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class MfBillingAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Student $student;
    private string $phone = '5545999990000';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mf_billing.enabled' => true,
            'mf_billing_local.enabled' => true,
            'mf_billing_local.worker_token' => str_repeat('X', 48),
            'mf_billing.whatsapp.enabled' => false,
            'app.timezone' => 'UTC',
        ]);
        $this->travelTo(CarbonImmutable::parse('2026-11-13 13:00:00', 'America/Sao_Paulo'));
        $this->admin = User::factory()->create(['role'=>UserRole::Admin,'active'=>true]);
        $this->student = Student::create([
            'name'=>'Ana Aluna', 'active'=>true,
            'created_by'=>$this->admin->id, 'updated_by'=>$this->admin->id,
        ]);
        Sanctum::actingAs($this->admin);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void { $this->travelBack(); parent::tearDown(); }

    private function configure(int $count=2): array
    {
        $settings = $this->getJson('/api/v1/billing/automation/settings')->assertOk()->json('data');
        $this->assertFalse($settings['enabled']);
        return $this->putJson('/api/v1/billing/automation/settings', [
            'revision'=>$settings['revision'], 'enabled'=>true,
            'sender_phone'=>'+55 45 99999-1111',
            'first_after_days'=>3, 'repeat_interval_days'=>5,
            'max_reminders'=>$count, 'send_time'=>'09:00',
            'templates'=>MfBillingAutoRules::DEFAULT_TEMPLATES,
        ])->assertOk()->json('data');
    }

    private function invoice(): MfBillingInvoice
    {
        $this->putJson('/api/v1/billing/students/'.$this->student->uuid, [
            'revision'=>0,'amount_cents'=>25000,'due_day'=>10,'start_month'=>'2026-11',
            'enabled'=>true,'whatsapp_phone'=>'+55 45 99999-0000',
            'whatsapp_consent'=>true,'consent_note'=>'Autorização de teste',
        ])->assertOk();
        $this->postJson('/api/v1/billing/generate',['month'=>'2026-11'])->assertOk();
        return MfBillingInvoice::firstOrFail();
    }

    private function worker(): void { $this->withHeader('X-MF-Billing-Worker', str_repeat('X', 48)); }
    private function ready(): void
    {
        // Regressao MySQL: o singleton permanece id=1 mesmo apos rollback.
        $this->assertSame(1, (int) MfBillingAutoSetting::query()->sole()->getKey());
        $this->worker();
        $this->postJson('/api/v1/billing/local-worker/heartbeat', [
            'phone'=>'5545999991111', 'ready'=>true,
        ])->assertOk()->assertJsonPath('data.matches_sender', true);
    }

    public function test_settings_require_admin_and_stale_revision_fails(): void
    {
        $s = $this->configure();
        $this->assertTrue($s['enabled']);
        $this->assertSame(2, $s['max_reminders']);
        $this->putJson('/api/v1/billing/automation/settings', [
            'revision'=>1,'enabled'=>true,'sender_phone'=>'+55 45 99999-1111',
            'first_after_days'=>3,'repeat_interval_days'=>5,'max_reminders'=>2,
            'send_time'=>'09:00','templates'=>MfBillingAutoRules::DEFAULT_TEMPLATES,
        ])->assertConflict();
        Sanctum::actingAs(User::factory()->create(['role'=>UserRole::Reception,'active'=>true]));
        $this->getJson('/api/v1/billing/automation/settings')->assertForbidden();
    }

    public function test_local_worker_requires_long_secret_and_disabled_flag_blocks(): void
    {
        $this->configure();
        $this->postJson('/api/v1/billing/local-worker/claim',['phone'=>$this->phone])->assertUnauthorized();
        $this->worker(); config(['mf_billing_local.enabled'=>false]);
        $this->postJson('/api/v1/billing/local-worker/claim',['phone'=>$this->phone])->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_due_claim_can_be_sent_once_and_second_uses_next_template(): void
    {
        $this->configure(); $i = $this->invoice(); $this->ready();
        $job = $this->postJson('/api/v1/billing/local-worker/claim',['phone'=>'5545999991111'])->assertOk()->json('data');
        $this->assertSame(1, $job['step']);
        $this->assertStringContainsString('Ana', $job['message']);
        $this->postJson('/api/v1/billing/local-worker/jobs/'.$job['uuid'].'/check',['phone'=>'5545999991111'])
            ->assertOk()->assertJsonPath('data.can_send', true);
        $this->postJson('/api/v1/billing/local-worker/jobs/'.$job['uuid'].'/ack',['status'=>'sent','provider_id'=>'local-test-id'])
            ->assertOk()->assertJsonPath('data.status', 'sent');
        $this->postJson('/api/v1/billing/local-worker/claim',['phone'=>'5545999991111'])->assertOk()->assertJsonPath('data', null);
        $this->travelTo(CarbonImmutable::parse('2026-11-18 13:00:00','America/Sao_Paulo'));
        $this->ready();
        $second = $this->postJson('/api/v1/billing/local-worker/claim',['phone'=>'5545999991111'])->assertOk()->json('data');
        $this->assertSame(2,$second['step']);
        $this->assertNotSame($job['message'],$second['message']);
        $this->assertDatabaseCount('mf_billing_auto_outbox',2);
        Http::assertNothingSent();
    }

    public function test_pay_before_next_step_prevents_claim(): void
    {
        $this->configure(); $invoice=$this->invoice(); $this->ready();
        $this->postJson('/api/v1/billing/invoices/'.$invoice->uuid.'/pay',[
            'revision'=>1,'paid_on'=>'2026-11-13','method'=>'pix',
        ])->assertOk();
        $this->postJson('/api/v1/billing/local-worker/claim',['phone'=>'5545999991111'])
            ->assertOk()->assertJsonPath('data',null);
        $this->assertDatabaseCount('mf_billing_auto_outbox',0);
    }

    public function test_payment_between_claim_and_final_check_cancels_job(): void
    {
        $this->configure(); $invoice=$this->invoice(); $this->ready();
        $job=$this->postJson('/api/v1/billing/local-worker/claim',['phone'=>'5545999991111'])->assertOk()->json('data');
        $this->postJson('/api/v1/billing/invoices/'.$invoice->uuid.'/pay',[
            'revision'=>1,'paid_on'=>'2026-11-13','method'=>'pix',
        ])->assertOk();
        $this->postJson('/api/v1/billing/local-worker/jobs/'.$job['uuid'].'/check',['phone'=>'5545999991111'])
            ->assertOk()->assertJsonPath('data.can_send',false);
        $this->assertSame('cancelled',MfBillingAutoOutbox::firstOrFail()->status);
    }

    public function test_opt_out_revokes_consent_and_prevents_future_claims(): void
    {
        $this->configure(); $this->invoice(); $this->ready();
        $this->postJson('/api/v1/billing/local-worker/opt-out',['phone'=>$this->phone])
            ->assertOk()->assertJsonPath('count',1);
        $this->assertNull(MfBillingAccount::firstOrFail()->consented_at);
        $this->postJson('/api/v1/billing/local-worker/claim',['phone'=>'5545999991111'])->assertOk()->assertJsonPath('data',null);
    }
}
