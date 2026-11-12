<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class MfBillingAutoOutbox extends Model
{
    protected $table = 'mf_billing_auto_outbox';
    protected $guarded = ['id'];
    protected $hidden = ['recipient', 'sender_phone', 'message'];

    protected function casts(): array
    {
        return [
            'recipient' => 'encrypted',
            'sender_phone' => 'encrypted',
            'message' => 'encrypted',
            'invoice_revision' => 'integer',
            'account_revision' => 'integer',
            'settings_revision' => 'integer',
            'template_index' => 'integer',
            'claimed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $job): void {
            if (! $job->uuid) {
                $job->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(MfBillingInvoice::class, 'invoice_id');
    }
}
