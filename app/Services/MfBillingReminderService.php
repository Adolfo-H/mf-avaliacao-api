<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\{MfBillingAccount, MfBillingInvoice, MfBillingReminder, User};
use App\Support\MfBillingRules;
use Illuminate\Support\Facades\{DB, Http};
use Illuminate\Http\Client\ConnectionException;
final class MfBillingReminderService {
    public function __construct(private MfBillingService $billing) {}
    public function cloudReady(): bool {
        $w=config('mf_billing.whatsapp');
        return $w['enabled'] && $w['template_approved'] && (bool)$w['token'] && (bool)$w['app_secret'] && (bool)$w['verify_token']
            && (bool)preg_match('/^v\d+\.\d+$/D',$w['graph_version']) && (bool)preg_match('/^\d+$/D',$w['phone_number_id'])
            && (bool)preg_match('/^[a-z0-9_]+$/D',$w['template']);
    }
    public function preview(MfBillingInvoice $invoice): array {
        $i=$invoice->load('account.student');
        $reasons=[];
        if ($i->state()!=='overdue') $reasons[]='Somente mensalidades vencidas e não pagas podem receber lembrete.';
        if (!$i->account->consented_at) $reasons[]='Autorização para lembretes não registrada.';
        if (!$i->account->whatsapp_phone) $reasons[]='WhatsApp do destinatário não configurado.';
        if (!$i->account->student->active || $i->account->student->isArchived() || !$i->account->enabled) $reasons[]='Aluno ou mensalidade suspensa.';
        $last=$i->reminders()->latest('id')->first();
        if ($i->reminders()->whereIn('status',['processing','unknown'])->exists()) $reasons[]='Há envio inconclusivo. Confira no provedor antes de reenviar.';
        if ($i->reminders()->whereNotIn('status',['failed','not_sent'])->where('created_at','>',now()->subHours((int)config('mf_billing.reminder_cooldown_hours',24)))->exists()) $reasons[]='Lembrete já preparado/enviado nas últimas 24 horas.';
        $text=MfBillingRules::message($i->account->student->name,$i->reference_month,$i->amount_cents,$i->due_date->format('Y-m-d'),config('mf_billing.studio_name'));
        return ['invoice'=>$this->billing->invoiceData($i),'account_revision'=>$i->account->revision,'message'=>$text,'recipient'=>$i->account->whatsapp_phone,'can_send'=>!$reasons,'blocked_reasons'=>$reasons,'cloud_configured'=>$this->cloudReady(),'last_reminder'=>$last?$this->data($last):null];
    }
    public function send(MfBillingInvoice $invoice, array $d, User $u): MfBillingReminder {
        $existing=MfBillingReminder::where('idempotency_key',$d['idempotency_key'])->first();
        if ($existing) { $this->sameRequest($existing,$invoice,$d,$u); return $existing; }
        abort_if($d['channel']==='cloud' && !$this->cloudReady(),422,'API oficial não configurada/aprovada. Use a abertura manual no WhatsApp Business.');
        $r=DB::transaction(function () use ($invoice,$d,$u) {
            $a=MfBillingAccount::whereKey($invoice->account_id)->lockForUpdate()->firstOrFail();
            $i=MfBillingInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $existing=MfBillingReminder::where('idempotency_key',$d['idempotency_key'])->first();
            if ($existing) { $this->sameRequest($existing,$i,$d,$u); return $existing; }
            $this->billing->revision((int)$d['revision'],$i->revision);
            $this->billing->revision((int)$d['account_revision'],$a->revision);
            $p=$this->preview($i);
            abort_unless($p['can_send'],409,implode(' ',$p['blocked_reasons']));
            abort_unless($d['confirmed_unpaid'],422,'Confirme a conferência manual antes do envio.');
            $r=MfBillingReminder::create(['invoice_id'=>$i->id,'actor_id'=>$u->id,'idempotency_key'=>$d['idempotency_key'],'invoice_revision'=>$i->revision,'reviewed_at'=>now(),'channel'=>$d['channel'],'status'=>$d['channel']==='cloud'?'processing':'prepared','recipient'=>$p['recipient'],'message'=>$p['message']]);
            $this->billing->event($a,$i,$u,'reminder_reviewed',['reminder_uuid'=>$r->uuid,'channel'=>$r->channel]);
            return $r;
        },3);
        // Nunca reexecutar HTTP por retry de transação ou por timeout.
        if ($d['channel']==='cloud' && $r->wasRecentlyCreated) $this->dispatch($r);
        return $r->fresh();
    }
    private function sameRequest(MfBillingReminder $r, MfBillingInvoice $i, array $d, User $u): void {
        abort_unless((int)$r->invoice_id===(int)$i->id && (int)$r->actor_id===(int)$u->id && $r->channel===$d['channel'],409,'Chave da operação já usada para outra solicitação.');
    }
    private function dispatch(MfBillingReminder $r): void {
        // Reserva persistida impede novo envio e impede baixa concorrente durante
        // a janela curta de dispatch. Revalida pagamento e consentimento agora.
        $i=$r->invoice()->with('account.student')->firstOrFail();
        if ($i->paid_on || $i->cancelled_at || !$i->account->consented_at || !$i->account->enabled || !$i->account->student->active || $i->account->student->isArchived() || $i->account->whatsapp_phone!==$r->recipient) {
            $r->update(['status'=>'not_sent','error_code'=>'changed_before_dispatch']); return;
        }
        $w=config('mf_billing.whatsapp');
        $params=[explode(' ',trim($i->account->student->name))[0],substr($i->reference_month,5,2).'/'.substr($i->reference_month,0,4),MfBillingRules::money($i->amount_cents),$i->due_date->format('d/m/Y')];
        try {
            $res=Http::withToken($w['token'])->acceptJson()->connectTimeout(5)->timeout(15)
                ->withOptions(['allow_redirects'=>false])->post('https://graph.facebook.com/'.$w['graph_version'].'/'.$w['phone_number_id'].'/messages',[
                    'messaging_product'=>'whatsapp','recipient_type'=>'individual','to'=>$r->recipient,'type'=>'template',
                    'template'=>['name'=>$w['template'],'language'=>['code'=>$w['language']], 'components'=>[['type'=>'body','parameters'=>array_map(fn($v)=>['type'=>'text','text'=>$v],$params)]]],
                ]);
            $id=$res->json('messages.0.id');
            if ($res->successful() && is_string($id) && $id!=='') $r->update(['status'=>'accepted','provider_id'=>$id]);
            elseif ($res->status()>=400 && $res->status()<500) $r->update(['status'=>'failed','error_code'=>'meta_'.(string)($res->json('error.code')??$res->status())]);
            else $r->update(['status'=>'unknown','error_code'=>'provider_result_uncertain']);
        } catch (ConnectionException) { $r->update(['status'=>'unknown','error_code'=>'network_result_uncertain']); }
        catch (\Throwable) { $r->update(['status'=>'unknown','error_code'=>'dispatch_result_uncertain']); }
        // Apenas códigos e identificadores; nunca token ou resposta bruta nos logs.
        $this->billing->event($i->account,$i,null,'reminder_result',['reminder_uuid'=>$r->uuid,'status'=>$r->status,'error_code'=>$r->error_code]);
    }
    public function data(MfBillingReminder $r): array {
        return ['uuid'=>$r->uuid,'channel'=>$r->channel,'status'=>$r->status,'created_at'=>$r->created_at?->toIso8601String(),'provider_id'=>$r->provider_id,'error_code'=>$r->error_code];
    }
    public function link(MfBillingReminder $r): ?string {
        if ($r->channel!=='manual' || !in_array($r->status,['prepared','manual_sent'],true)) return null;
        $i=$r->invoice()->with('account.student')->firstOrFail();
        if ($i->paid_on || $i->cancelled_at || !$i->account->consented_at || !$i->account->enabled || !$i->account->student->active || $i->account->student->isArchived() || $i->account->whatsapp_phone!==$r->recipient) return null;
        return 'https://wa.me/'.$r->recipient.'?text='.rawurlencode($r->message);
    }
}
