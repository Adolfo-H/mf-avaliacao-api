<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\{MfBillingAccount, MfBillingReminder};
use App\Services\MfBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonImmutable;
final class MfBillingWebhookController extends Controller {
    public function verify(Request $r) {
        $token=(string)config('mf_billing.whatsapp.verify_token');
        abort_unless($token!=='' && $r->query('hub_mode')==='subscribe' && hash_equals($token,(string)$r->query('hub_verify_token')),403);
        $challenge=(string)$r->query('hub_challenge'); abort_unless(preg_match('/^\d{1,100}$/D',$challenge),400);
        return response($challenge,200)->header('Content-Type','text/plain')->header('Cache-Control','no-store');
    }
    public function receive(Request $r, MfBillingService $billing) {
        $secret=(string)config('mf_billing.whatsapp.app_secret'); $raw=$r->getContent();
        abort_if(strlen($raw)>262144,413);
        abort_unless($secret!=='' && hash_equals('sha256='.hash_hmac('sha256',$raw,$secret),(string)$r->header('X-Hub-Signature-256')),403);
        $payload=json_decode($raw,true); abort_unless(is_array($payload),400);
        foreach ($payload['entry']??[] as $entry) foreach ($entry['changes']??[] as $change) {
            $v=$change['value']??[];
            if ((string)($v['metadata']['phone_number_id']??'')!==(string)config('mf_billing.whatsapp.phone_number_id')) continue;
            foreach ($v['statuses']??[] as $status) {
                if (!isset($status['id'],$status['status'])) continue;
                DB::transaction(function () use($status) {
                    $m=MfBillingReminder::where('provider_id',$status['id'])->lockForUpdate()->first(); if (!$m) return;
                    $rank=['processing'=>0,'unknown'=>0,'failed'=>0,'accepted'=>1,'sent'=>2,'delivered'=>3,'read'=>4];
                    $next=(string)$status['status'];
                    if (!array_key_exists($next,$rank)) return;
                    if (($rank[$next]??0)>=($rank[$m->status]??0) || ($next==='failed' && in_array($m->status,['accepted','sent'],true))) {
                        $m->status=$next;
                        $m->provider_at=isset($status['timestamp']) && ctype_digit((string)$status['timestamp']) ? CarbonImmutable::createFromTimestamp((int)$status['timestamp'],'UTC') : now();
                        $m->error_code=$next==='failed' ? 'meta_'.(string)($status['errors'][0]['code']??'delivery') : null;
                        $m->save();
                    }
                });
            }
            foreach ($v['messages']??[] as $message) {
                $word=mb_strtoupper(trim((string)($message['text']['body']??'')));
                if (!in_array($word,['SAIR','PARAR','STOP','CANCELAR LEMBRETES'],true)) continue;
                $from=(string)($message['from']??''); if (!preg_match('/^[1-9]\d{7,14}$/D',$from)) continue;
                $hash=hash_hmac('sha256',$from,(string)config('app.key'));
                DB::transaction(function () use($hash,$billing) {
                    foreach (MfBillingAccount::where('phone_hash',$hash)->whereNotNull('consented_at')->lockForUpdate()->get() as $a) {
                        $a->consented_at=null; $a->consent_note=null; $a->revision++; $a->save();
                        $billing->event($a,null,null,'whatsapp_optout',['source'=>'signed_webhook']);
                    }
                });
            }
        }
        return response()->json(['received'=>true])->header('Cache-Control','no-store');
    }
}
