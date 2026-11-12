<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\MfBillingAutoRun;
use App\Models\User;
use App\Services\MfBillingAutoService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class MfBillingRunStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mf_billing.enabled' => true,
            'mf_billing_local.enabled' => true,
            'mf_billing_local.worker_token' => str_repeat('f', 64),
            'app.timezone' => 'UTC',
        ]);
        $this->travelTo(CarbonImmutable::parse('2026-11-11 07:00:00', 'America/Sao_Paulo'));
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Admin, 'active' => true]));
        $settings = app(MfBillingAutoService::class)->settings();
        $settings->enabled = true;
        $settings->sender_phone = '5545999994892';
        $settings->save();
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function operationStatus(): array
    {
        return $this->getJson('/api/v1/billing/automation/settings')->assertOk()->json('data.operation');
    }

    private function report(string $status, array $extra = []): void
    {
        $this->withHeader('X-MF-Billing-Worker', str_repeat('f', 64));
        $this->postJson('/api/v1/billing/local-worker/run-status', array_merge([
            'run_id' => '43099591614', 'status' => $status,
        ], $extra))->assertOk();
    }

    public function test_schedule_waiting_then_start_and_success_with_zero_messages(): void
    {
        self::assertSame('waiting', $this->operationStatus()['state']);
        $this->travelTo(CarbonImmutable::parse('2026-11-11 08:10:00', 'America/Sao_Paulo'));
        $this->report('started');
        self::assertSame('starting', $this->operationStatus()['state']);
        $this->report('succeeded', ['sent_count' => 0]);
        $summary = $this->operationStatus();
        self::assertSame('waiting', $summary['state']);
        self::assertSame(0, $summary['last_success_sent_count']);
        self::assertSame('succeeded', $summary['last_status']);
        $this->report('succeeded', ['sent_count' => 10]); // reenvio HTTP: nao altera
        self::assertSame(0, MfBillingAutoRun::query()->sole()->sent_count);
        self::assertSame(1, MfBillingAutoRun::query()->count());
    }

    public function test_failure_is_visible_and_old_run_does_not_block_forever(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-11 08:10:00', 'America/Sao_Paulo'));
        $this->report('started');
        $this->travelTo(CarbonImmutable::parse('2026-11-11 08:41:00', 'America/Sao_Paulo'));
        self::assertSame('failed', $this->operationStatus()['state']);
        $this->report('failed', ['error_code' => 'worker']);
        self::assertSame('worker', $this->operationStatus()['last_error_code']);
        self::assertSame('failed', $this->operationStatus()['state']);
    }

    public function test_missing_run_after_grace_is_unconfirmed_not_invented_failure(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-11 11:08:00', 'America/Sao_Paulo'));
        self::assertSame('unconfirmed', $this->operationStatus()['state']);
        self::assertNull($this->operationStatus()['last_status']);
    }

    public function test_reporting_requires_private_worker_token(): void
    {
        $this->postJson('/api/v1/billing/local-worker/run-status', [
            'run_id' => '43099591614', 'status' => 'started',
        ])->assertUnauthorized();
        self::assertSame(0, MfBillingAutoRun::query()->count());
        $this->withHeader('X-MF-Billing-Worker', str_repeat('f', 64));
        $this->postJson('/api/v1/billing/local-worker/run-status', [
            'run_id' => '43099591614', 'status' => 'failed', 'error_code' => 'token_expired',
        ])->assertUnprocessable();
        self::assertSame(0, MfBillingAutoRun::query()->count());
    }

    public function test_disabling_automation_returns_disabled_but_preserves_history(): void
    {
        $this->report('started');
        $this->report('succeeded', ['sent_count' => 2]);
        $settings = app(MfBillingAutoService::class)->settings();
        $settings->enabled = false;
        $settings->save();
        self::assertSame('disabled', $this->operationStatus()['state']);
        self::assertSame(2, $this->operationStatus()['last_success_sent_count']);
    }
}
