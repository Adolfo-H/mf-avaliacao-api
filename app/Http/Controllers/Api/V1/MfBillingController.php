<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MfBillingAccount;
use App\Models\MfBillingEvent;
use App\Models\MfBillingInvoice;
use App\Models\MfBillingReminder;
use App\Models\Student;
use App\Services\MfBillingReminderService;
use App\Services\MfBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class MfBillingController extends Controller
{
    public function __construct(private MfBillingService $billing, private MfBillingReminderService $reminders) {}

    private function month(Request $r): string
    {
        return $r->validate(['month' => ['required', 'regex:/^20\d{2}-(0[1-9]|1[0-2])$/D']])['month'];
    }

    public function settings(): JsonResponse
    {
        return response()->json(['data' => ['studio_name' => config('mf_billing.studio_name'), 'studio_phone' => config('mf_billing.studio_phone'), 'timezone' => 'America/Sao_Paulo', 'today' => now('America/Sao_Paulo')->format('Y-m-d'), 'current_month' => now('America/Sao_Paulo')->format('Y-m'), 'cloud_configured' => $this->reminders->cloudReady(), 'manual_available' => true, 'template' => config('mf_billing.whatsapp.template'), 'cooldown_hours' => config('mf_billing.reminder_cooldown_hours'), 'note' => 'Nenhum envio é automático por calendário. A mensagem exige revisão e confirmação.']]);
    }

    public function generate(Request $r): JsonResponse
    {
        return response()->json(['created' => $this->billing->generate($this->month($r), $r->user())]);
    }

    public function index(Request $r): JsonResponse
    {
        $month = $this->month($r);
        $d = $r->validate(['status' => ['nullable', Rule::in(['all', 'paid', 'overdue', 'due_today', 'open', 'cancelled'])], 'search' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1']);
        $today = now('America/Sao_Paulo')->format('Y-m-d');
        $base = MfBillingInvoice::query()->where('reference_month', $month);
        $sums = (clone $base)->selectRaw('COUNT(*) as total, SUM(CASE WHEN paid_on IS NOT NULL AND cancelled_at IS NULL THEN amount_cents ELSE 0 END) as received_cents, SUM(CASE WHEN paid_on IS NULL AND cancelled_at IS NULL AND due_date < ? THEN amount_cents ELSE 0 END) as overdue_cents, SUM(CASE WHEN paid_on IS NULL AND cancelled_at IS NULL THEN amount_cents ELSE 0 END) as outstanding_cents, SUM(CASE WHEN paid_on IS NOT NULL AND cancelled_at IS NULL THEN 1 ELSE 0 END) as paid_count, SUM(CASE WHEN paid_on IS NULL AND cancelled_at IS NULL AND due_date < ? THEN 1 ELSE 0 END) as overdue_count', [$today, $today])->first();
        $status = $d['status'] ?? 'all';
        if ($status === 'paid') {
            $base->whereNotNull('paid_on')->whereNull('cancelled_at');
        } elseif ($status === 'cancelled') {
            $base->whereNotNull('cancelled_at');
        } elseif ($status !== 'all') {
            $base->whereNull('paid_on')->whereNull('cancelled_at');
            $base->where('due_date', match ($status) {
                'overdue' => '<','due_today' => '=',default => '>'
            }, $today);
        }
        if (! empty($d['search'])) {
            $base->whereHas('account.student', fn ($q) => $q->where('name', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $d['search']).'%'));
        }
        $page = $base->with('account.student')->orderBy('due_date')->orderBy('id')->paginate(25);

        // Cadastros com inicio futuro nao sao faturas. Exibi-los no financeiro
        // evita que uma configuracao salva pareca ter desaparecido.
        // Nao incluir no total em aberto nem nas cobrancas do mes selecionado.
        $scheduled = collect();
        if ($status === 'all' && $page->currentPage() === 1) {
            $scheduledQuery = MfBillingAccount::query()
                ->with('student')
                ->where('enabled', true)
                ->where('start_month', '>', $month)
                ->whereHas('student', fn ($q) => $q->where('active', true)->whereNull('archived_at'));
            if (! empty($d['search'])) {
                $escapedSearch = str_replace(['%', '_'], ['\\%', '\\_'], $d['search']);
                $scheduledQuery->whereHas('student', fn ($q) => $q->where('name', 'like', '%'.$escapedSearch.'%'));
            }
            $scheduled = $scheduledQuery->orderBy('start_month')->orderBy('id')
                ->limit(30)->get()->map(fn (MfBillingAccount $a) => [
                    'student_uuid' => $a->student->uuid,
                    'student_name' => $a->student->name,
                    'start_month' => $a->start_month,
                    'due_day' => (int) $a->due_day,
                    'amount_cents' => (int) $a->amount_cents,
                ]);
        }

        return response()->json(['data' => $page->getCollection()->map(fn ($i) => $this->billing->invoiceData($i)), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()], 'summary' => collect($sums->getAttributes())->map(fn ($v) => (int) $v), 'today' => $today, 'scheduled_accounts' => $scheduled]);
    }

    public function account(Student $student): JsonResponse
    {
        $a = MfBillingAccount::where('student_id', $student->id)->first();
        $invoices = $a ? $a->invoices()->with('account.student')->orderByDesc('reference_month')->limit(60)->get()->map(fn ($i) => $this->billing->invoiceData($i)) : [];
        $events = $a ? MfBillingEvent::where('account_id', $a->id)->orderByDesc('id')->limit(60)->get(['id', 'action', 'details', 'actor_id', 'created_at']) : [];

        return response()->json(['data' => $this->billing->accountData($a, $student), 'invoices' => $invoices, 'events' => $events]);
    }

    public function configure(Request $r, Student $student): JsonResponse
    {
        $d = $r->validate(['revision' => 'required|integer|min:0', 'amount_cents' => 'required|integer|min:1|max:100000000', 'due_day' => 'required|integer|min:1|max:31', 'reminder_grace_day' => 'nullable|integer|min:1|max:31', 'max_reminders' => 'sometimes|integer|min:1|max:3', 'reminder_interval_days' => 'sometimes|integer|min:2|max:30', 'start_month' => ['required', 'regex:/^20\d{2}-(0[1-9]|1[0-2])$/D'], 'enabled' => 'required|boolean', 'whatsapp_phone' => 'nullable|string|max:30', 'whatsapp_consent' => 'required|boolean', 'consent_note' => 'nullable|string|max:500']);
        try {
            $a = $this->billing->configure($student, $d, $r->user());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->billing->accountData($a, $student)]);
    }

    public function pay(Request $r, MfBillingInvoice $invoice): JsonResponse
    {
        $d = $r->validate(['revision' => 'required|integer|min:1', 'paid_on' => 'required|date_format:Y-m-d|after_or_equal:2000-01-01|before_or_equal:'.now('America/Sao_Paulo')->format('Y-m-d'), 'method' => ['required', Rule::in(['pix', 'cash', 'card', 'transfer', 'other'])]]);

        return response()->json(['data' => $this->billing->invoiceData($this->billing->changeInvoice($invoice, $d, $r->user(), 'pay'))]);
    }

    public function reverse(Request $r, MfBillingInvoice $invoice): JsonResponse
    {
        return $this->reasonAction($r, $invoice, 'reverse');
    }

    public function cancel(Request $r, MfBillingInvoice $invoice): JsonResponse
    {
        return $this->reasonAction($r, $invoice, 'cancel');
    }

    public function restore(Request $r, MfBillingInvoice $invoice): JsonResponse
    {
        $d = $r->validate([
            'revision' => 'required|integer|min:1',
            'amount_cents' => 'required|integer|min:1|max:100000000',
            'due_date' => 'required|date_format:Y-m-d|after_or_equal:2000-01-01',
            'reason' => 'required|string|min:5|max:500',
        ]);

        return response()->json([
            'data' => $this->billing->invoiceData($this->billing->changeInvoice($invoice, $d, $r->user(), 'restore')),
        ]);
    }

    private function reasonAction(Request $r, MfBillingInvoice $invoice, string $type): JsonResponse
    {
        $d = $r->validate(['revision' => 'required|integer|min:1', 'reason' => 'required|string|min:5|max:500']);

        return response()->json(['data' => $this->billing->invoiceData($this->billing->changeInvoice($invoice, $d, $r->user(), $type))]);
    }

    public function terms(Request $r, MfBillingInvoice $invoice): JsonResponse
    {
        $d = $r->validate(['revision' => 'required|integer|min:1', 'amount_cents' => 'required|integer|min:1|max:100000000', 'due_date' => 'required|date_format:Y-m-d|after_or_equal:2000-01-01', 'reason' => 'required|string|min:5|max:500']);

        return response()->json(['data' => $this->billing->invoiceData($this->billing->changeInvoice($invoice, $d, $r->user(), 'adjust'))]);
    }

    public function preview(MfBillingInvoice $invoice): JsonResponse
    {
        return response()->json(['data' => $this->reminders->preview($invoice)]);
    }

    public function send(Request $r, MfBillingInvoice $invoice): JsonResponse
    {
        $d = $r->validate(['revision' => 'required|integer|min:1', 'account_revision' => 'required|integer|min:1', 'idempotency_key' => 'required|uuid', 'channel' => ['required', Rule::in(['manual', 'cloud'])], 'confirmed_unpaid' => 'required|accepted']);
        $a = $this->reminders->send($invoice, $d, $r->user());

        return response()->json(['data' => $this->reminders->data($a), 'whatsapp_url' => $this->reminders->link($a)]);
    }

    public function reminderHistory(MfBillingInvoice $invoice): JsonResponse
    {
        return response()->json(['data' => $invoice->reminders()->orderByDesc('id')->limit(100)->get()->map(fn ($r) => $this->reminders->data($r))]);
    }

    public function resolveReminder(Request $r, MfBillingReminder $reminder): JsonResponse
    {
        $d = $r->validate(['outcome' => ['required', Rule::in(['manual_sent', 'not_sent', 'accepted'])], 'reason' => 'required|string|min:5|max:500']);
        $updated = DB::transaction(function () use ($r, $reminder, $d) {
            $m = MfBillingReminder::whereKey($reminder->id)->lockForUpdate()->firstOrFail();
            if ($m->channel === 'cloud') {
                abort_unless($r->user()->isAdmin(), 403);
                abort_if($m->status === 'processing' && $m->created_at->gt(now()->subMinutes(2)), 409, 'Envio ainda em processamento.');
                abort_unless(in_array($m->status, ['unknown', 'processing'], true) && in_array($d['outcome'], ['not_sent', 'accepted'], true), 422, 'Somente resultado inconclusivo pode ser conciliado.');
            } else {
                abort_unless($m->status === 'prepared' && in_array($d['outcome'], ['manual_sent', 'not_sent'], true), 422, 'Registro manual já encerrado.');
            }
            $m->status = $d['outcome'];
            $m->save();
            $i = $m->invoice()->with('account')->firstOrFail();
            $this->billing->event($i->account, $i, $r->user(), 'reminder_reconciled', ['reminder_uuid' => $m->uuid, 'outcome' => $d['outcome'], 'reason' => $d['reason']]);

            return $m;
        });

        return response()->json(['data' => $this->reminders->data($updated)]);
    }
}
