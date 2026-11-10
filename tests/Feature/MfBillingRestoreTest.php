<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\MfBillingEvent;
use App\Models\MfBillingInvoice;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class MfBillingRestoreTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mf_billing.enabled' => true,
            'mf_billing.whatsapp.enabled' => false,
            'mf_billing_local.enabled' => false,
            'app.timezone' => 'UTC',
        ]);
        $this->travelTo(CarbonImmutable::parse('2026-11-13 12:00:00', 'America/Sao_Paulo'));
        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'active' => true]);
        $this->student = Student::create([
            'name' => 'Aluno de Homologação',
            'active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
        Sanctum::actingAs($this->admin);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function invoice(): MfBillingInvoice
    {
        $this->putJson('/api/v1/billing/students/'.$this->student->uuid, [
            'revision' => 0,
            'amount_cents' => 10000,
            'due_day' => 12,
            'start_month' => '2026-11',
            'enabled' => true,
            'whatsapp_phone' => '+55 45 99999-0000',
            'whatsapp_consent' => true,
            'consent_note' => 'Autorização simulada para teste automatizado',
        ])->assertOk();
        $this->postJson('/api/v1/billing/generate', ['month' => '2026-11'])->assertOk();
        return MfBillingInvoice::query()->firstOrFail();
    }

    private function cancel(MfBillingInvoice $invoice): void
    {
        $this->postJson('/api/v1/billing/invoices/'.$invoice->uuid.'/cancel', [
            'revision' => $invoice->revision,
            'reason' => 'Cancelamento registrado por engano',
        ])->assertOk()->assertJsonPath('data.status', 'cancelled');
    }

    public function test_cancelled_month_can_be_restored_with_day_nine_without_duplicate_invoice(): void
    {
        $invoice = $this->invoice();
        $uuid = $invoice->uuid;
        $this->cancel($invoice);
        $this->postJson('/api/v1/billing/generate', ['month' => '2026-11'])
            ->assertOk()->assertJsonPath('created', 0);

        $this->postJson('/api/v1/billing/invoices/'.$uuid.'/restore', [
            'revision' => 2,
            'amount_cents' => 12000,
            'due_date' => '2026-11-09',
            'reason' => 'Reativação solicitada pela administração',
        ])->assertOk()
            ->assertJsonPath('data.uuid', $uuid)
            ->assertJsonPath('data.revision', 3)
            ->assertJsonPath('data.due_date', '2026-11-09')
            ->assertJsonPath('data.amount_cents', 12000)
            ->assertJsonPath('data.status', 'overdue');

        $this->getJson('/api/v1/billing/invoices?month=2026-11&status=overdue')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.uuid', $uuid);
        $this->assertDatabaseCount('mf_billing_invoices', 1);
        $this->assertTrue(MfBillingEvent::query()->where('action', 'invoice_cancel')->exists());
        $this->assertTrue(MfBillingEvent::query()->where('action', 'invoice_restore')->exists());
        Http::assertNothingSent();
    }

    public function test_restore_requires_admin_and_matching_revision(): void
    {
        $invoice = $this->invoice();
        $this->cancel($invoice);
        $url = '/api/v1/billing/invoices/'.$invoice->uuid.'/restore';
        $payload = [
            'revision' => 2,
            'amount_cents' => 10000,
            'due_date' => '2026-11-09',
            'reason' => 'Corrigindo cancelamento anterior',
        ];
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Reception, 'active' => true]));
        $this->postJson($url, $payload)->assertForbidden();
        Sanctum::actingAs($this->admin);
        $this->postJson($url, array_replace($payload, ['revision' => 1]))->assertConflict();
        $this->assertNotNull($invoice->fresh()->cancelled_at);
    }

    public function test_restore_only_accepts_same_month_and_only_cancelled_invoices(): void
    {
        $invoice = $this->invoice();
        $url = '/api/v1/billing/invoices/'.$invoice->uuid.'/restore';
        $payload = [
            'revision' => 1,
            'amount_cents' => 10000,
            'due_date' => '2026-11-09',
            'reason' => 'Corrigindo cancelamento anterior',
        ];
        $this->postJson($url, $payload)->assertConflict();
        $this->cancel($invoice);
        $this->postJson($url, array_replace($payload, [
            'revision' => 2,
            'due_date' => '2026-12-09',
        ]))->assertUnprocessable();
        $this->assertSame('cancelled', $invoice->fresh()->state());
    }

    public function test_existing_prepared_reminder_blocks_reactivation(): void
    {
        $invoice = $this->invoice();
        $this->postJson('/api/v1/billing/invoices/'.$invoice->uuid.'/reminders', [
            'revision' => 1,
            'account_revision' => 1,
            'idempotency_key' => (string) Str::uuid(),
            'channel' => 'manual',
            'confirmed_unpaid' => true,
        ])->assertOk();
        $this->cancel($invoice);
        $this->postJson('/api/v1/billing/invoices/'.$invoice->uuid.'/restore', [
            'revision' => 2,
            'amount_cents' => 10000,
            'due_date' => '2026-11-09',
            'reason' => 'Corrigindo cancelamento anterior',
        ])->assertConflict();
        $this->assertSame('cancelled', $invoice->fresh()->state());
        $this->assertDatabaseCount('mf_billing_invoices', 1);
        Http::assertNothingSent();
    }
}
