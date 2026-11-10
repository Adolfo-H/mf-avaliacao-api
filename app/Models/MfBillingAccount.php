<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class MfBillingAccount extends Model {
    protected $table = 'mf_billing_accounts';
    protected $guarded = ['id'];
    protected $hidden = ['phone_hash'];
    protected function casts(): array { return ['amount_cents'=>'integer','due_day'=>'integer','enabled'=>'boolean','revision'=>'integer','whatsapp_phone'=>'encrypted','consent_note'=>'encrypted','consented_at'=>'datetime']; }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function invoices(): HasMany { return $this->hasMany(MfBillingInvoice::class, 'account_id'); }
}
