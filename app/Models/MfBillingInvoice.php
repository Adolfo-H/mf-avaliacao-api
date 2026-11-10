<?php
namespace App\Models;
use App\Support\MfBillingRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
class MfBillingInvoice extends Model {
    protected $table = 'mf_billing_invoices'; protected $guarded = ['id'];
    protected function casts(): array { return ['amount_cents'=>'integer','revision'=>'integer','due_date'=>'date','paid_on'=>'date','payment_recorded_at'=>'datetime','cancelled_at'=>'datetime','cancellation_reason'=>'encrypted']; }
    protected static function booted(): void { static::creating(function (self $i): void { if (!$i->uuid) $i->uuid=(string)Str::uuid(); }); }
    public function getRouteKeyName(): string { return 'uuid'; }
    public function account(): BelongsTo { return $this->belongsTo(MfBillingAccount::class, 'account_id'); }
    public function reminders(): HasMany { return $this->hasMany(MfBillingReminder::class, 'invoice_id'); }
    public function state(): string { return MfBillingRules::status($this->due_date->format('Y-m-d'), (bool)$this->paid_on, (bool)$this->cancelled_at, now('America/Sao_Paulo')->format('Y-m-d')); }
}
