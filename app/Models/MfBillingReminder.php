<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
class MfBillingReminder extends Model {
    protected $table = 'mf_billing_reminders'; protected $guarded = ['id'];
    protected $hidden = ['recipient','message','idempotency_key'];
    protected function casts(): array { return ['recipient'=>'encrypted','message'=>'encrypted','reviewed_at'=>'datetime','provider_at'=>'datetime','invoice_revision'=>'integer']; }
    protected static function booted(): void { static::creating(function (self $r): void { if (!$r->uuid) $r->uuid=(string)Str::uuid(); }); }
    public function getRouteKeyName(): string { return 'uuid'; }
    public function invoice(): BelongsTo { return $this->belongsTo(MfBillingInvoice::class, 'invoice_id'); }
}
