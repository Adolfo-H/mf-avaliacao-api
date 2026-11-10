<?php
declare(strict_types=1);
namespace Tests\Feature;
use App\Enums\UserRole;
use App\Models\{Student,User,MfBillingAccount,MfBillingInvoice,MfBillingReminder,MfBillingEvent};
use App\Services\MfBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
final class MfBillingModuleTest extends TestCase {
    use RefreshDatabase;
    protected User $admin;
    protected Student $student;
    protected function setUp(): void {
        parent::setUp();
        config(['mf_billing.enabled'=>true,'mf_billing.whatsapp.enabled'=>false,'app.timezone'=>'UTC']);
        $this->travelTo(now()->setDate(2026,11,12)->setTime(12,0));
        $this->admin=User::factory()->create(['role'=>UserRole::Admin,'active'=>true]);
        $this->student=Student::create(['name'=>'Aluno Fictício','active'=>true,'created_by'=>$this->admin->id]);
        Sanctum::actingAs($this->admin);
        Http::preventStrayRequests();
    }
    protected function tearDown(): void { $this->travelBack(); parent::tearDown(); }
    private function payload(array $extra=[]): array { return array_replace(['revision'=>0,'amount_cents'=>25000,'due_day'=>10,'start_month'=>'2026-11','enabled'=>true,'whatsapp_phone'=>'+55 45 99999-0000','whatsapp_consent'=>true,'consent_note'=>'Autorização fictícia em teste'], $extra); }
    private function accountUrl(): string {return '/api/v1/billing/students/'.$this->student->uuid;}
    private function invoice(array $extra=[]): MfBillingInvoice {
        $this->putJson($this->accountUrl(),$this->payload($extra))->assertOk();
        $this->postJson('/api/v1/billing/generate',['month'=>'2026-11'])->assertOk();
        return MfBillingInvoice::firstOrFail();
    }
    private function remind(MfBillingInvoice $i,array $extra=[]): array {return array_replace(['revision'=>$i->revision,'account_revision'=>1,'idempotency_key'=>(string)Str::uuid(),'channel'=>'manual','confirmed_unpaid'=>true],$extra);}
    private function cloud(): void {config(['mf_billing.whatsapp.enabled'=>true,'mf_billing.whatsapp.template_approved'=>true,'mf_billing.whatsapp.graph_version'=>'v99.0','mf_billing.whatsapp.phone_number_id'=>'123456','mf_billing.whatsapp.token'=>'TEST_ONLY','mf_billing.whatsapp.app_secret'=>'test-secret','mf_billing.whatsapp.verify_token'=>'verify-test','mf_billing.whatsapp.template'=>'mf_mensalidade_pendente']);}
    private function hook(array $value,bool $valid=true) {
        $raw=json_encode(['entry'=>[['changes'=>[['value'=>array_merge(['metadata'=>['phone_number_id'=>'123456']],$value)]]]]],JSON_UNESCAPED_UNICODE);
        return $this->call('POST','/api/v1/billing/whatsapp/webhook',[],[],[],['CONTENT_TYPE'=>'application/json','HTTP_X_HUB_SIGNATURE_256'=>$valid?'sha256='.hash_hmac('sha256',$raw,'test-secret'):'invalid'], $raw);
    }
    public function test_evaluator_and_inactive_staff_cannot_access_finance(): void {
        Sanctum::actingAs(User::factory()->create(['role'=>UserRole::Evaluator,'active'=>true]));
        $this->getJson('/api/v1/billing/settings')->assertForbidden();
        Sanctum::actingAs(User::factory()->create(['role'=>UserRole::Admin,'active'=>false]));
        $this->getJson('/api/v1/billing/settings')->assertForbidden();
    }
    public function test_reception_can_use_financial_area_but_not_reverse(): void {
        $i=$this->invoice(); Sanctum::actingAs(User::factory()->create(['role'=>UserRole::Reception,'active'=>true]));
        $this->getJson('/api/v1/billing/settings')->assertOk();
        $this->postJson("/api/v1/billing/invoices/{$i->uuid}/pay",['revision'=>1,'paid_on'=>'2026-11-12','method'=>'pix'])->assertOk();
        $this->postJson("/api/v1/billing/invoices/{$i->uuid}/reverse",['revision'=>2,'reason'=>'Conferência'])->assertForbidden();
    }
    public function test_feature_flag_is_closed_by_default(): void {config(['mf_billing.enabled'=>false]);$this->getJson('/api/v1/billing/settings')->assertStatus(503);}
    public function test_one_invoice_per_account_per_month_and_no_sends_on_generation(): void {
        $this->invoice();$this->postJson('/api/v1/billing/generate',['month'=>'2026-11'])->assertOk()->assertJsonPath('created',0);
        $this->assertDatabaseCount('mf_billing_invoices',1);$this->assertDatabaseCount('mf_billing_reminders',0);Http::assertNothingSent();
    }
    public function test_future_competence_and_invalid_configuration_rejected(): void {
        $this->postJson('/api/v1/billing/generate',['month'=>'2026-12'])->assertUnprocessable();
        $this->putJson($this->accountUrl(),$this->payload(['due_day'=>0]))->assertUnprocessable();
        $this->putJson($this->accountUrl(),$this->payload(['whatsapp_consent'=>true,'consent_note'=>'']))->assertUnprocessable();
    }
    public function test_config_change_does_not_rewrite_invoices(): void {
        $this->invoice();$this->putJson($this->accountUrl(),$this->payload(['revision'=>1,'amount_cents'=>30000,'due_day'=>20]))->assertOk();
        $i=MfBillingInvoice::firstOrFail();$this->assertSame(25000,$i->amount_cents);$this->assertSame('2026-11-10',$i->due_date->format('Y-m-d'));
    }
    public function test_payment_is_manual_idempotent_by_revision_and_clears_overdue(): void {
        $i=$this->invoice();$url="/api/v1/billing/invoices/{$i->uuid}/pay";
        $this->postJson($url,['revision'=>1,'paid_on'=>'2026-11-12','method'=>'pix'])->assertOk()->assertJsonPath('data.status','paid');
        $this->postJson($url,['revision'=>1,'paid_on'=>'2026-11-12','method'=>'pix'])->assertConflict();
        $this->getJson('/api/v1/billing/invoices?month=2026-11&status=overdue')->assertOk()->assertJsonCount(0,'data');
        $this->assertTrue($this->student->fresh()->active);Http::assertNothingSent();
    }
    public function test_paid_invoice_cannot_receive_message(): void {
        $i=$this->invoice();$this->postJson("/api/v1/billing/invoices/{$i->uuid}/pay",['revision'=>1,'paid_on'=>'2026-11-12','method'=>'pix'])->assertOk();
        $this->postJson("/api/v1/billing/invoices/{$i->uuid}/reminders",$this->remind($i,['revision'=>2]))->assertConflict();Http::assertNothingSent();
    }
    public function test_no_permission_or_no_review_blocks_message(): void {
        $i=$this->invoice(['whatsapp_consent'=>false]);
        $this->postJson("/api/v1/billing/invoices/{$i->uuid}/reminders",$this->remind($i))->assertConflict();
        $this->postJson("/api/v1/billing/invoices/{$i->uuid}/reminders",$this->remind($i,['confirmed_unpaid'=>false]))->assertUnprocessable();
    }
    public function test_due_today_and_future_invoices_cannot_be_reminded(): void {
        $i=$this->invoice(['due_day'=>12]);
        $this->getJson("/api/v1/billing/invoices/{$i->uuid}/preview")->assertOk()->assertJsonPath('data.can_send',false);
        $this->postJson("/api/v1/billing/invoices/{$i->uuid}/reminders",$this->remind($i))->assertConflict();
    }
    public function test_manual_prepare_is_not_marked_sent_and_replay_is_safe(): void {
        $i=$this->invoice();$d=$this->remind($i);$url="/api/v1/billing/invoices/{$i->uuid}/reminders";
        $r=$this->postJson($url,$d)->assertOk()->assertJsonPath('data.status','prepared');
        $this->assertStringStartsWith('https://wa.me/5545999990000?text=',$r->json('whatsapp_url'));
        $this->postJson($url,$d)->assertOk()->assertJsonPath('data.uuid',$r->json('data.uuid'));
        $this->assertDatabaseCount('mf_billing_reminders',1);Http::assertNothingSent();
        $this->postJson($url,$this->remind($i))->assertConflict();
    }
    public function test_manual_resolution_and_opt_out_take_effect(): void {
        $i=$this->invoice();$r=$this->postJson("/api/v1/billing/invoices/{$i->uuid}/reminders",$this->remind($i))->assertOk();
        $this->postJson('/api/v1/billing/reminders/'.$r->json('data.uuid').'/resolve',['outcome'=>'manual_sent','reason'=>'Conferido no WhatsApp'])->assertOk()->assertJsonPath('data.status','manual_sent');
        $this->putJson($this->accountUrl(),$this->payload(['revision'=>1,'whatsapp_consent'=>false]))->assertOk();
        $this->getJson("/api/v1/billing/invoices/{$i->uuid}/preview")->assertOk()->assertJsonPath('data.can_send',false);
    }
    public function test_stale_revision_does_not_change_invoice(): void {
        $i=$this->invoice();$this->patchJson("/api/v1/billing/invoices/{$i->uuid}/terms",['revision'=>10,'amount_cents'=>500,'due_date'=>'2026-11-15','reason'=>'Ajuste fictício'])->assertConflict();
        $this->assertSame(25000,$i->fresh()->amount_cents);
    }
    public function test_reversal_requires_reason_and_preserves_audit(): void {
        $i=$this->invoice();$base="/api/v1/billing/invoices/{$i->uuid}";
        $this->postJson($base.'/pay',['revision'=>1,'paid_on'=>'2026-11-12','method'=>'pix'])->assertOk();
        $this->postJson($base.'/reverse',['revision'=>2,'reason'=>''])->assertUnprocessable();
        $this->postJson($base.'/reverse',['revision'=>2,'reason'=>'Baixa registrada no aluno errado'])->assertOk()->assertJsonPath('data.status','overdue');
        $this->assertTrue(MfBillingEvent::where('action','invoice_pay')->exists());$this->assertTrue(MfBillingEvent::where('action','invoice_reverse')->exists());
    }
    public function test_cloud_configuration_is_required(): void {
        $i=$this->invoice();$this->postJson("/api/v1/billing/invoices/{$i->uuid}/reminders",$this->remind($i,['channel'=>'cloud']))->assertUnprocessable();Http::assertNothingSent();
    }
    public function test_cloud_sends_one_template_and_does_not_claim_delivery(): void {
        $this->cloud();$i=$this->invoice();Http::fake(['graph.facebook.com/*'=>Http::response(['messages'=>[['id'=>'wamid.test']]],200)]);
        $d=$this->remind($i,['channel'=>'cloud']);$url="/api/v1/billing/invoices/{$i->uuid}/reminders";
        $this->postJson($url,$d)->assertOk()->assertJsonPath('data.status','accepted')->assertJsonPath('whatsapp_url',null);
        $this->postJson($url,$d)->assertOk();Http::assertSentCount(1);
        Http::assertSent(fn($r)=>$r['type']==='template' && count($r['template']['components'][0]['parameters'])===4);
    }
    public function test_uncertain_provider_result_is_not_automatically_retried(): void {
        $this->cloud();$i=$this->invoice();Http::fake(['graph.facebook.com/*'=>Http::response([],500)]);
        $d=$this->remind($i,['channel'=>'cloud']);$url="/api/v1/billing/invoices/{$i->uuid}/reminders";
        $this->postJson($url,$d)->assertOk()->assertJsonPath('data.status','unknown');
        $this->postJson($url,$d)->assertOk();$this->postJson($url,$this->remind($i,['channel'=>'cloud']))->assertConflict();Http::assertSentCount(1);
    }
    public function test_webhook_signature_and_delivery_order(): void {
        $this->cloud();$i=$this->invoice();Http::fake(['graph.facebook.com/*'=>Http::response(['messages'=>[['id'=>'wamid.delivery']]],200)]);
        $r=$this->postJson("/api/v1/billing/invoices/{$i->uuid}/reminders",$this->remind($i,['channel'=>'cloud']))->assertOk();
        $value=['statuses'=>[['id'=>'wamid.delivery','status'=>'delivered','timestamp'=>'1794484800']]];
        $this->hook($value,false)->assertForbidden();$this->hook($value)->assertOk();
        $this->hook(['statuses'=>[['id'=>'wamid.delivery','status'=>'sent','timestamp'=>'1794484790']]])->assertOk();
        $this->assertSame('delivered',MfBillingReminder::where('uuid',$r->json('data.uuid'))->firstOrFail()->status);
    }
    public function test_signed_optout_revokes_consent_only_for_the_sender(): void {
        $this->cloud();$this->invoice();
        $this->hook(['messages'=>[['from'=>'5545999990000','text'=>['body'=>'SAIR']]]])->assertOk();
        $this->assertNull(MfBillingAccount::firstOrFail()->consented_at);
        $this->assertTrue(MfBillingEvent::where('action','whatsapp_optout')->exists());
    }
    public function test_signed_payload_for_other_phone_is_ignored(): void {
        $this->cloud();$this->invoice();$this->hook(['metadata'=>['phone_number_id'=>'other'],'messages'=>[['from'=>'5545999990000','text'=>['body'=>'SAIR']]]])->assertOk();
        $this->assertNotNull(MfBillingAccount::firstOrFail()->consented_at);
    }
    public function test_contact_change_after_review_requires_a_new_review(): void {
        $i=$this->invoice();
        $this->putJson($this->accountUrl(),$this->payload(['revision'=>1,'whatsapp_phone'=>'+55 45 99999-0001']))->assertOk();
        $this->postJson("/api/v1/billing/invoices/{$i->uuid}/reminders",$this->remind($i,['account_revision'=>1]))->assertConflict();
        Http::assertNothingSent();
    }
    public function test_history_does_not_expose_recipient_or_message_and_never_changes_clinical_state(): void {
        $i=$this->invoice();$this->postJson("/api/v1/billing/invoices/{$i->uuid}/reminders",$this->remind($i))->assertOk();
        $r=$this->getJson("/api/v1/billing/invoices/{$i->uuid}/reminders")->assertOk();
        $this->assertArrayNotHasKey('recipient',$r->json('data.0'));$this->assertArrayNotHasKey('message',$r->json('data.0'));
        $this->assertTrue($this->student->fresh()->active);
    }
}
