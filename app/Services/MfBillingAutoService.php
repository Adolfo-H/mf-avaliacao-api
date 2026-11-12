<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MfBillingAccount;
use App\Models\MfBillingAutoOutbox;
use App\Models\MfBillingAutoSetting;
use App\Models\MfBillingInvoice;
use App\Models\MfBillingReminder;
use App\Models\User;
use App\Support\MfBillingAutoRules;
use App\Support\MfBillingRules;
use App\Support\MfBillingSenderPhone;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Agenda persistente com uma única reserva por cobrança/etapa.
 * O worker local é o único responsável por falar com o WhatsApp Web.
 * Não tenta reenviar automaticamente uma etapa cuja entrega seja incerta.
 */
final class MfBillingAutoService
{
    public function __construct(private MfBillingService $billing) {}

    public function settings(): MfBillingAutoSetting
    {
        $existing = MfBillingAutoSetting::query()->find(1);
        if ($existing !== null) {
            return $existing;
        }

        // O ID 1 deve ser explicito: o modelo protege "id" contra mass assignment.
        // Em MySQL, o AUTO_INCREMENT nao volta ao numero anterior apos rollback.
        $setting = new MfBillingAutoSetting([
            'enabled' => false,
            'sender_phone' => null,
            'first_after_days' => 3,
            'repeat_interval_days' => 5,
            'max_reminders' => 1,
            'send_time' => '08:00',
            'templates' => MfBillingAutoRules::DEFAULT_TEMPLATES,
            'revision' => 1,
            'worker_connected' => false,
        ]);
        $setting->id = 1;

        try {
            $setting->save();
        } catch (UniqueConstraintViolationException $e) {
            // Outra requisicao pode ter inicializado a mesma configuracao.
            return MfBillingAutoSetting::query()->findOrFail(1);
        }

        return $setting;
    }

    public function summary(MfBillingAutoSetting $s): array
    {
        return [
            'enabled' => $s->enabled,
            'sender_phone' => $s->sender_phone ?? '',
            'first_after_days' => $s->first_after_days,
            'repeat_interval_days' => $s->repeat_interval_days,
            'max_reminders' => $s->max_reminders,
            'send_time' => $s->send_time,
            'templates' => $s->templates,
            'revision' => $s->revision,
            'timezone' => 'America/Sao_Paulo',
            'server_armed' => (bool) config('mf_billing_local.enabled'),
            'worker_online' => $s->worker_connected && $s->worker_seen_at?->gt(now()->subSeconds((int) config('mf_billing_local.max_worker_age_seconds', 150))),
            'worker_seen_at' => $s->worker_seen_at?->toIso8601String(),
            'worker_matches_sender' => MfBillingSenderPhone::matches($s->sender_phone, $s->worker_phone),
            'daily_cap' => (int) config('mf_billing_local.max_daily_reminders', 10),
            'note' => 'WhatsApp Web não oficial, agendado no GitHub. Não permanece online fora das execuções e pode sofrer restrições da plataforma.',
        ];
    }

    public function configure(array $data, User $user): MfBillingAutoSetting
    {
        $templates = MfBillingAutoRules::validateTemplates($data['templates'], 3);
        $phone = MfBillingRules::phone($data['sender_phone'] ?? null);
        if ($data['enabled'] && ! $phone) {
            throw ValidationException::withMessages(['sender_phone' => 'Informe o número remetente com DDI.']);
        }

        return DB::transaction(function () use ($data, $templates, $phone, $user) {
            $s = MfBillingAutoSetting::query()->whereKey(1)->lockForUpdate()->first();
            if (! $s) {
                $s = $this->settings();
            }
            abort_if($s->revision !== (int) $data['revision'], 409, 'Configuração alterada em outra sessão. Recarregue.');
            if ($phone !== $s->sender_phone) {
                $s->worker_phone = null;
                $s->worker_connected = false;
                $s->worker_seen_at = null;
            }
            $s->enabled = (bool) $data['enabled'];
            $s->sender_phone = $phone;
            $s->first_after_days = (int) $data['first_after_days'];
            $s->repeat_interval_days = (int) $data['repeat_interval_days'];
            $s->max_reminders = (int) $data['max_reminders'];
            $s->send_time = '08:00'; // Mesmo horario da rotina agendada; o painel edita apenas mensagens.
            $s->templates = $templates;
            $s->updated_by = $user->id;
            $s->revision++;
            $s->save();

            return $s;
        }, 3);
    }

    /** Recebe o número efetivamente conectado no aparelho pareado. */
    public function heartbeat(string $connectedPhone, bool $ready): array
    {
        $s = $this->settings();
        $phone = MfBillingRules::phone($connectedPhone);
        $s->worker_phone = $phone;
        $s->worker_connected = $ready;
        $s->worker_seen_at = now();
        $s->save();

        return [
            'enabled' => $s->enabled && (bool) config('mf_billing_local.enabled'),
            'matches_sender' => MfBillingSenderPhone::matches($s->sender_phone, $phone),
            'sender_phone' => $s->sender_phone,
            'poll_seconds' => 60,
        ];
    }

    /** Uma reserva será criada SOMENTE quando um worker autenticado solicitar. */
    public function claim(string $connectedPhone): ?array
    {
        return DB::transaction(function () use ($connectedPhone) {
            $s = MfBillingAutoSetting::query()->whereKey(1)->lockForUpdate()->first();
            if (! $s || ! $s->enabled || ! $s->sender_phone || ! MfBillingSenderPhone::matches($s->sender_phone, MfBillingRules::phone($connectedPhone))
                || ! $s->worker_connected || ! $s->worker_seen_at?->gt(now()->subSeconds(150))) {
                return null;
            }

            // Gera a competência corrente mesmo se Fernando não abrir o painel.
            if (! $s->last_generated_at || $s->last_generated_at->lt(now()->subHour())) {
                $this->billing->generate(now('America/Sao_Paulo')->format('Y-m'));
                $s->last_generated_at = now();
                $s->save();
            }
            $start = now('America/Sao_Paulo')->startOfDay()->utc();
            if (MfBillingAutoOutbox::query()->where('claimed_at', '>=', $start)->count() >= (int) config('mf_billing_local.max_daily_reminders', 10)) {
                return null;
            }
            $now = now('America/Sao_Paulo')->timestamp;
            $maxLag = (int) config('mf_billing_local.max_dispatch_lag_hours', 48) * 3600;
            $candidates = MfBillingInvoice::query()
                ->whereNull('paid_on')->whereNull('cancelled_at')
                ->where('due_date', '<', now('America/Sao_Paulo')->format('Y-m-d'))
                ->where('due_date', '>=', now('America/Sao_Paulo')->subDays(110)->format('Y-m-d'))
                ->whereHas('account', fn ($q) => $q->where('enabled', true)->whereNotNull('consented_at')
                    ->whereHas('student', fn ($s) => $s->where('active', true)->whereNull('archived_at')))
                ->orderBy('due_date')->orderBy('id')->limit(150)->get();
            foreach ($candidates as $candidate) {
                // Mesma ordem de locks do restante da API: conta -> cobrança.
                $a = MfBillingAccount::query()->with('student')->whereKey($candidate->account_id)->lockForUpdate()->first();
                $i = MfBillingInvoice::query()->whereKey($candidate->id)->lockForUpdate()->first();
                if (! $a || ! $i || $i->paid_on || $i->cancelled_at || ! $a->enabled || ! $a->consented_at || ! $a->whatsapp_phone
                    || ! $a->student->active || $a->student->isArchived()) {
                    continue;
                }
                $prior = MfBillingAutoOutbox::query()->where('invoice_id', $i->id)->orderBy('step')->get();
                if ($prior->contains(fn ($j) => $j->status !== 'sent')) {
                    continue;
                }
                $step = $prior->count() + 1;
                if ($step > (int) $a->max_reminders) {
                    continue;
                }
                $due = $i->due_date->format('Y-m-d');
                // O intervalo é contado da data REAL do aviso anterior, não
                // de uma previsão que poderia ter atrasado por falta de conexão.
                if ($step === 1) {
                    $at = MfBillingAutoRules::firstReminderAt($due, $i->reference_month, $a->reminder_grace_day, $s->first_after_days, $s->send_time);
                } else {
                    $previous = $prior->last();
                    if (! $previous?->completed_at) {
                        continue;
                    }
                    $sentDate = $previous->completed_at->copy()->timezone('America/Sao_Paulo')->format('Y-m-d');
                    $at = MfBillingAutoRules::schedule($sentDate, (int) $a->reminder_interval_days, (int) $a->reminder_interval_days, 1, $s->send_time);
                }
                if ($now < $at->getTimestamp() || $now > $at->getTimestamp() + $maxLag) {
                    continue;
                }
                // Não sobrepor avisos manuais/Meta da implementação anterior
                // na mesma cobrança, mesmo que tenham mais de 24 horas.
                if (MfBillingReminder::query()->where('invoice_id', $i->id)
                    ->whereNotIn('status', ['failed', 'not_sent'])->exists()) {
                    continue;
                }
                // Três modelos sao ALTERNATIVAS de texto, não três envios obrigatórios.
                // Escolha criptograficamente aleatória, registrada junto à reserva,
                // evitando repetir o mesmo texto dentro desta mensalidade.
                $used = $prior->pluck('template_index')
                    ->filter(static fn ($index) => $index !== null)
                    ->map(static fn ($index) => (int) $index)->all();
                $options = array_values(array_diff([0, 1, 2], $used));
                if ($options === []) {
                    continue;
                }
                $templateIndex = $options[random_int(0, count($options) - 1)];
                $template = $s->templates[$templateIndex] ?? null;
                if (! is_string($template) || trim($template) === '') {
                    continue;
                }
                $msg = MfBillingAutoRules::render($template, [
                    'nome' => explode(' ', trim($a->student->name))[0] ?: $a->student->name,
                    'mes' => substr($i->reference_month, 5, 2).'/'.substr($i->reference_month, 0, 4),
                    'data' => $i->due_date->format('d/m/Y'),
                    'valor' => MfBillingRules::money($i->amount_cents),
                    'estudio' => (string) config('mf_billing.studio_name'),
                    'dias_atraso' => (string) max(0, abs(now('America/Sao_Paulo')->startOfDay()->diffInDays($i->due_date->copy()->startOfDay(), false))),
                ]);
                $j = MfBillingAutoOutbox::create([
                    'invoice_id' => $i->id, 'step' => $step, 'status' => 'reserved', 'template_index' => $templateIndex,
                    'recipient' => $a->whatsapp_phone, 'sender_phone' => $s->sender_phone,
                    'message' => $msg, 'invoice_revision' => $i->revision,
                    'account_revision' => $a->revision, 'settings_revision' => $s->revision,
                    'claimed_at' => now(),
                ]);
                $this->billing->event($a, $i, null, 'auto_reminder_reserved', ['step' => $step, 'job_uuid' => $j->uuid]);

                return ['uuid' => $j->uuid, 'invoice_uuid' => $i->uuid, 'step' => $step,
                    'recipient' => $j->recipient, 'message' => $j->message];
            }

            return null;
        }, 3);
    }

    public function check(MfBillingAutoOutbox $job, string $connectedPhone): array
    {
        return DB::transaction(function () use ($job, $connectedPhone) {
            $i = MfBillingInvoice::query()->whereKey($job->invoice_id)->firstOrFail();
            $a = MfBillingAccount::query()->with('student')->whereKey($i->account_id)->lockForUpdate()->firstOrFail();
            $i = MfBillingInvoice::query()->whereKey($job->invoice_id)->lockForUpdate()->firstOrFail();
            $j = MfBillingAutoOutbox::query()->whereKey($job->id)->lockForUpdate()->firstOrFail();
            $s = MfBillingAutoSetting::query()->whereKey(1)->first();
            abort_unless($j->status === 'reserved', 409, 'Etapa já confirmada ou encerrada.');
            $valid = $s && $s->enabled && $j->settings_revision === $s->revision
                && MfBillingSenderPhone::matches($s->sender_phone, MfBillingRules::phone($connectedPhone))
                && $j->sender_phone === $s->sender_phone && $a->whatsapp_phone === $j->recipient
                && $a->revision === $j->account_revision && $i->revision === $j->invoice_revision
                && ! $i->paid_on && ! $i->cancelled_at && $a->enabled && $a->consented_at
                && $a->student->active && ! $a->student->isArchived()
                && $j->claimed_at->gte(now()->subMinutes(3));
            if (! $valid) {
                $j->status = 'cancelled';
                $j->error_code = 'condition_changed';
                $j->completed_at = now();
                $j->save();

                return ['can_send' => false, 'reason' => 'Cobrança ou configuração alterada. Nenhuma mensagem deve ser enviada.'];
            }
            $j->status = 'sending';
            $j->confirmed_at = now();
            $j->save();

            return ['can_send' => true, 'uuid' => $j->uuid, 'recipient' => $j->recipient, 'message' => $j->message];
        }, 3);
    }

    public function acknowledge(MfBillingAutoOutbox $job, string $status, ?string $providerId, ?string $code): array
    {
        return DB::transaction(function () use ($job, $status, $providerId, $code) {
            $j = MfBillingAutoOutbox::query()->whereKey($job->id)->lockForUpdate()->firstOrFail();
            if (in_array($j->status, ['sent', 'unknown', 'not_sent'], true)) {
                return ['uuid' => $j->uuid, 'status' => $j->status];
            }
            abort_unless($j->status === 'sending', 409, 'O envio não foi confirmado para este registro.');
            $j->status = $status;
            $j->provider_id = $providerId ? mb_substr($providerId, 0, 255) : null;
            $j->error_code = $code ? mb_substr($code, 0, 80) : null;
            $j->completed_at = now();
            $j->save();
            $i = $j->invoice()->with('account')->firstOrFail();
            $this->billing->event($i->account, $i, null, 'auto_reminder_'.$status, ['job_uuid' => $j->uuid, 'step' => $j->step]);

            return ['uuid' => $j->uuid, 'status' => $j->status];
        }, 3);
    }

    /** Resposta SAIR/PARAR recebida no WhatsApp pessoal pareado. */
    public function optOut(string $phone): int
    {
        $normalized = MfBillingRules::phone($phone);
        // O WhatsApp pode devolver telefones brasileiros com ou sem o nono digito.
        // Nunca altera o numero cadastrado; consulta apenas hashes equivalentes.
        $phones = [$normalized];
        if (str_starts_with($normalized, '55') && strlen($normalized) === 12) {
            $phones[] = substr($normalized, 0, 4).'9'.substr($normalized, 4);
        } elseif (str_starts_with($normalized, '55') && strlen($normalized) === 13 && $normalized[4] === '9') {
            $phones[] = substr($normalized, 0, 4).substr($normalized, 5);
        }
        $hashes = array_map(
            static fn (string $value): string => hash_hmac('sha256', $value, (string) config('app.key')),
            array_unique($phones)
        );

        return DB::transaction(function () use ($hashes) {
            $count = 0;
            foreach (MfBillingAccount::query()->whereIn('phone_hash', $hashes)->whereNotNull('consented_at')->lockForUpdate()->get() as $a) {
                $a->consented_at = null;
                $a->consent_note = null;
                $a->revision++;
                $a->save();
                $this->billing->event($a, null, null, 'whatsapp_optout', ['source' => 'local_whatsapp_worker']);
                $count++;
            }

            return $count;
        }, 3);
    }

    public function history(): array
    {
        return MfBillingAutoOutbox::query()->with('invoice.account.student')
            ->orderByDesc('id')->limit(50)->get()->map(fn ($j) => [
                'uuid' => $j->uuid, 'step' => $j->step, 'status' => $j->status,
                'student_name' => $j->invoice->account->student->name,
                'reference_month' => $j->invoice->reference_month,
                'created_at' => $j->created_at?->toIso8601String(),
                'completed_at' => $j->completed_at?->toIso8601String(),
                'error_code' => $j->error_code,
            ])->all();
    }
}
