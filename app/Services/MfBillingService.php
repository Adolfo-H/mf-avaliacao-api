<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MfBillingAccount;
use App\Models\MfBillingEvent;
use App\Models\MfBillingInvoice;
use App\Models\Student;
use App\Models\User;
use App\Support\MfBillingRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class MfBillingService
{
    public function event(MfBillingAccount $a, ?MfBillingInvoice $i, ?User $u, string $action, array $details = []): void
    {
        MfBillingEvent::create(['account_id' => $a->id, 'invoice_id' => $i?->id, 'actor_id' => $u?->id, 'action' => $action, 'details' => $details, 'created_at' => now()]);
    }

    public function configure(Student $student, array $data, User $user): MfBillingAccount
    {
        return DB::transaction(function () use ($student, $data, $user) {
            // A trava do cadastro serializa também a criação da primeira conta.
            $student = Student::query()->whereKey($student->id)->lockForUpdate()->firstOrFail();
            $a = MfBillingAccount::query()->where('student_id', $student->id)->lockForUpdate()->first();
            if ($a) {
                $this->revision((int) $data['revision'], $a->revision);
            } else {
                abort_if((int) $data['revision'] !== 0, 409, 'Configuração foi modificada. Recarregue.');
            }
            if ($data['enabled']) {
                abort_if(! $student->active || $student->isArchived(), 422, 'Aluno inativo/arquivado: não habilite novas mensalidades.');
            }
            $phone = MfBillingRules::phone($data['whatsapp_phone'] ?? null);
            $consent = (bool) $data['whatsapp_consent'];
            if ($consent && (! $phone || empty(trim($data['consent_note'] ?? '')))) {
                throw ValidationException::withMessages(['consent_note' => 'Registre telefone e origem da autorização para lembretes.']);
            }
            if (! $a) {
                $a = new MfBillingAccount(['student_id' => $student->id]);
            }
            // Opcional por aluno: o dia 14 significa aguardar o pagamento
            // ate 23:59 do dia 14 e so considerar aviso a partir do dia 15.
            // O frontend antigo pode omitir este campo; nesse caso mantemos o valor salvo.
            $limitDay = array_key_exists('reminder_grace_day', $data)
                ? $data['reminder_grace_day'] : $a->reminder_grace_day;
            if ($limitDay !== null && (int) $limitDay < (int) $data['due_day']) {
                throw ValidationException::withMessages([
                    'reminder_grace_day' => 'O limite sem lembrete nao pode ser anterior ao dia do vencimento.',
                ]);
            }
            // O valor sera aplicado depois de registrar o estado anterior.
            $old = $a->exists ? ['amount_cents' => $a->amount_cents, 'due_day' => $a->due_day, 'reminder_grace_day' => $a->reminder_grace_day, 'enabled' => $a->enabled, 'start_month' => $a->start_month, 'consented' => (bool) $a->consented_at] : null;
            $a->reminder_grace_day = $limitDay;
            $a->amount_cents = $data['amount_cents'];
            $a->due_day = $data['due_day'];
            // Competência inicial não pode ser reescrita com cobranças existentes.
            if ($a->exists && $a->start_month !== $data['start_month'] && $a->invoices()->exists()) {
                throw ValidationException::withMessages(['start_month' => 'Competência inicial possui histórico; mantenha o mês original.']);
            }
            $a->start_month = $data['start_month'];
            $a->enabled = $data['enabled'];
            $changedPhone = $a->whatsapp_phone !== $phone;
            $a->whatsapp_phone = $phone;
            $a->phone_hash = $phone ? hash_hmac('sha256', $phone, (string) config('app.key')) : null;
            $a->consented_at = $consent ? (! $changedPhone && $a->consented_at ? $a->consented_at : now()) : null;
            $a->consent_note = $consent ? trim($data['consent_note']) : null;
            $a->revision = $a->exists ? $a->revision + 1 : 1;
            $a->save();
            $this->event($a, null, $user, $old ? 'account_updated' : 'account_created', ['before' => $old, 'after' => ['amount_cents' => $a->amount_cents, 'due_day' => $a->due_day, 'reminder_grace_day' => $a->reminder_grace_day, 'enabled' => $a->enabled, 'consented' => $consent]]);

            return $a;
        }, 3);
    }

    public function generate(string $month, ?User $user = null): int
    {
        MfBillingRules::month($month);
        $current = now('America/Sao_Paulo')->format('Y-m');
        abort_if($month > $current, 422, 'A geração desta versão é limitada à competência atual ou anterior.');
        $count = 0;
        MfBillingAccount::query()->where('enabled', true)->where('start_month', '<=', $month)
            ->whereHas('student', fn ($q) => $q->where('active', true)->whereNull('archived_at'))
            ->select('id')->chunkById(100, function ($batch) use ($month, $user, &$count) {
                foreach ($batch as $item) {
                    $count += DB::transaction(function () use ($item, $month, $user) {
                        $a = MfBillingAccount::query()->with('student')->whereKey($item->id)->lockForUpdate()->first();
                        if (! $a || ! $a->enabled || ! $a->student->active || $a->student->isArchived() || $a->start_month > $month) {
                            return 0;
                        }
                        if ($a->invoices()->where('reference_month', $month)->exists()) {
                            return 0;
                        }
                        $i = MfBillingInvoice::create(['account_id' => $a->id, 'reference_month' => $month, 'amount_cents' => $a->amount_cents, 'due_date' => MfBillingRules::dueDate($month, $a->due_day), 'revision' => 1]);
                        $this->event($a, $i, $user, 'invoice_created', ['month' => $month, 'amount_cents' => $i->amount_cents, 'due_date' => $i->due_date->format('Y-m-d')]);

                        return 1;
                    }, 3);
                }
            });

        return $count;
    }

    public function revision(int $expected, int $actual): void
    {
        abort_if($expected !== $actual, 409, 'O registro mudou em outra sessão. Recarregue antes de continuar.');
    }

    public function ensureNotSending(MfBillingInvoice $i): void
    {
        abort_if($i->reminders()->where('status', 'processing')->where('created_at', '>', now()->subMinutes(2))->exists(), 409, 'Um lembrete está em envio. Aguarde a resposta e atualize antes de registrar o pagamento.');
    }

    public function changeInvoice(MfBillingInvoice $invoice, array $d, User $user, string $operation): MfBillingInvoice
    {
        return DB::transaction(function () use ($invoice, $d, $user, $operation) {
            // Ordem de locks igual à configuração/envio: conta, depois cobrança.
            $a = MfBillingAccount::query()->whereKey($invoice->account_id)->lockForUpdate()->firstOrFail();
            $i = MfBillingInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $this->revision((int) $d['revision'], $i->revision);
            $this->ensureNotSending($i);
            $before = ['amount_cents' => $i->amount_cents, 'due_date' => $i->due_date->format('Y-m-d'), 'paid_on' => $i->paid_on?->format('Y-m-d'), 'payment_method' => $i->payment_method, 'state' => $i->state()];
            if ($operation === 'pay') {
                abort_if($i->paid_on || $i->cancelled_at, 409, 'Esta mensalidade já foi baixada ou cancelada.');
                $i->paid_on = $d['paid_on'];
                $i->payment_method = $d['method'];
                $i->paid_by = $user->id;
                $i->payment_recorded_at = now();
            } elseif ($operation === 'reverse') {
                abort_unless($user->isAdmin(), 403, 'Somente administrador pode desfazer uma baixa.');
                abort_unless($i->paid_on, 409, 'Não há pagamento para estornar no sistema.');
                $i->paid_on = null;
                $i->payment_method = null;
                $i->paid_by = null;
                $i->payment_recorded_at = null;
            } elseif ($operation === 'cancel') {
                abort_unless($user->isAdmin(), 403);
                abort_if($i->paid_on || $i->cancelled_at, 409, 'Não é possível cancelar esta mensalidade.');
                $i->cancelled_at = now();
                $i->cancellation_reason = $d['reason'];
            } elseif ($operation === 'restore') {
                // Reativar a MESMA fatura: unicidade aluno+competencia permanece protegida.
                abort_unless($user->isAdmin(), 403, 'Somente administradores podem reativar mensalidades.');
                abort_unless($i->cancelled_at && ! $i->paid_on, 409, 'Somente mensalidades canceladas podem ser reativadas.');
                abort_unless(substr($d['due_date'], 0, 7) === $i->reference_month, 422, 'O vencimento precisa estar no mês da mensalidade.');
                // Sem repeticao de aviso ja preparado/enviado sob a cobranca antiga.
                $hasReminder = $i->reminders()->whereNotIn('status', ['failed', 'not_sent'])->exists();
                $hasAutoJob = Schema::hasTable('mf_billing_auto_outbox')
                    && DB::table('mf_billing_auto_outbox')->where('invoice_id', $i->id)->exists();
                abort_if($hasReminder || $hasAutoJob, 409, 'Esta mensalidade possui mensagens anteriores. Confira o histórico antes de reativar.');
                $i->cancelled_at = null;
                $i->cancellation_reason = null;
                $i->amount_cents = $d['amount_cents'];
                $i->due_date = $d['due_date'];
            } else {
                abort_if($i->paid_on || $i->cancelled_at, 409, 'Somente mensalidades em aberto podem ser ajustadas.');
                $i->amount_cents = $d['amount_cents'];
                $i->due_date = $d['due_date'];
            }
            $i->revision++;
            $i->save();
            $this->event($a, $i, $user, 'invoice_'.$operation, ['before' => $before, 'reason' => $d['reason'] ?? null, 'paid_on' => $d['paid_on'] ?? null, 'method' => $d['method'] ?? null, 'amount_cents' => $i->amount_cents, 'due_date' => $i->due_date->format('Y-m-d')]);

            return $i->load('account.student');
        }, 3);
    }

    public function invoiceData(MfBillingInvoice $i): array
    {
        $s = $i->account->student;

        return ['uuid' => $i->uuid, 'reference_month' => $i->reference_month, 'due_date' => $i->due_date->format('Y-m-d'), 'amount_cents' => $i->amount_cents, 'status' => $i->state(), 'revision' => $i->revision, 'paid_on' => $i->paid_on?->format('Y-m-d'), 'payment_method' => $i->payment_method, 'student' => ['uuid' => $s->uuid, 'name' => $s->name, 'active' => $s->active, 'archived' => $s->isArchived()], 'whatsapp_consent' => (bool) $i->account->consented_at, 'phone_configured' => (bool) $i->account->whatsapp_phone];
    }

    public function accountData(?MfBillingAccount $a, Student $s): array
    {
        return ['student' => ['uuid' => $s->uuid, 'name' => $s->name, 'active' => $s->active, 'archived' => $s->isArchived()], 'configured' => (bool) $a, 'revision' => $a?->revision ?? 0, 'amount_cents' => $a?->amount_cents ?? null, 'due_day' => $a?->due_day ?? 10, 'reminder_grace_day' => $a?->reminder_grace_day, 'start_month' => $a?->start_month ?? now('America/Sao_Paulo')->format('Y-m'), 'enabled' => $a?->enabled ?? true, 'whatsapp_phone' => $a?->whatsapp_phone ?? ($s->mobile_phone ?? ''), 'whatsapp_consent' => (bool) $a?->consented_at, 'consent_note' => $a?->consent_note ?? '', 'consented_at' => $a?->consented_at?->toIso8601String()];
    }
}
