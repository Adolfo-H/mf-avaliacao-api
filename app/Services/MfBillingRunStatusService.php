<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MfBillingAutoRun;
use App\Models\MfBillingAutoSetting;
use App\Support\MfBillingSenderPhone;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estado persistido de execucoes GitHub Actions; nao confundir com heartbeat
 * do WhatsApp, que necessariamente expira quando a maquina agendada desliga.
 */
final class MfBillingRunStatusService
{
    private const TZ = 'America/Sao_Paulo';
    private const SCHEDULE_HOUR = 8;
    private const SCHEDULE_MINUTE = 7;
    private const ATTENTION_AFTER_HOURS = 3;

    /** O token e verificado no middleware EnsureMfBillingLocalWorker. */
    public function record(array $payload): MfBillingAutoRun
    {
        abort_unless(Schema::hasTable('mf_billing_auto_runs'), 503, 'Migracao do status agendado ainda nao aplicada.');

        return DB::transaction(static function () use ($payload): MfBillingAutoRun {
            $id = $payload['run_id'];
            $status = $payload['status'];
            $run = MfBillingAutoRun::query()->where('run_id', $id)->lockForUpdate()->first();
            if ($run === null) {
                $run = MfBillingAutoRun::create([
                    'run_id' => $id,
                    'status' => 'started',
                    'started_at' => now(),
                ]);
            }

            // Um registro finalizado e imutavel: repeticao de HTTP nao altera o resultado.
            if ($run->status !== 'started' || $status === 'started') {
                return $run;
            }

            $run->status = $status;
            $run->sent_count = $status === 'succeeded' ? (int) ($payload['sent_count'] ?? 0) : null;
            $run->error_code = $status === 'failed' ? ($payload['error_code'] ?? 'unknown') : null;
            $run->finished_at = now();
            $run->save();

            return $run;
        }, 3);
    }

    public function summary(MfBillingAutoSetting $settings): array
    {
        $now = CarbonImmutable::now(self::TZ);
        $today = $now->startOfDay();
        $scheduledToday = $today->setTime(self::SCHEDULE_HOUR, self::SCHEDULE_MINUTE);
        $next = $now->lessThan($scheduledToday) ? $scheduledToday : $scheduledToday->addDay();

        $base = [
            'state' => 'setup_required',
            'schedule_time' => '08:07',
            'next_run_at' => $next->toIso8601String(),
            'last_started_at' => null,
            'last_finished_at' => null,
            'last_status' => null,
            'last_sent_count' => null,
            'last_success_at' => null,
            'last_success_sent_count' => null,
            'last_error_code' => null,
            'worker_live' => false,
        ];

        if (!Schema::hasTable('mf_billing_auto_runs')) {
            return $base;
        }

        $recent = MfBillingAutoRun::query()->orderByDesc('id')->first();
        $lastSuccess = MfBillingAutoRun::query()->where('status', 'succeeded')->orderByDesc('id')->first();
        $workerLive = (bool) $settings->worker_connected
            && $settings->worker_seen_at?->gt(now()->subSeconds((int) config('mf_billing_local.max_worker_age_seconds', 150)))
            && MfBillingSenderPhone::matches($settings->sender_phone, $settings->worker_phone);

        $state = 'waiting';
        if (!$settings->enabled || !(bool) config('mf_billing_local.enabled')) {
            $state = 'disabled';
        } elseif ($recent !== null && $recent->status === 'started') {
            $fresh = $recent->started_at?->gt(now()->subMinutes(30));
            $state = $fresh ? ($workerLive ? 'processing' : 'starting') : 'failed';
        } elseif ($recent !== null && $recent->status === 'failed') {
            $state = 'failed';
        } elseif ($now->greaterThanOrEqualTo($scheduledToday->addHours(self::ATTENTION_AFTER_HOURS))
            && ($recent === null || $recent->started_at->copy()->timezone(self::TZ)->lt($today))) {
            // Falta de confirmacao nao prova que o GitHub falhou. Pode ter atrasado.
            $state = 'unconfirmed';
        }

        return array_replace($base, [
            'state' => $state,
            'last_started_at' => $recent?->started_at?->toIso8601String(),
            'last_finished_at' => $recent?->finished_at?->toIso8601String(),
            'last_status' => $recent?->status,
            'last_sent_count' => $recent?->sent_count,
            'last_success_at' => $lastSuccess?->finished_at?->toIso8601String(),
            'last_success_sent_count' => $lastSuccess?->sent_count,
            'last_error_code' => $recent?->error_code,
            'worker_live' => $workerLive,
        ]);
    }
}
