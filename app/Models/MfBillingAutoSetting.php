<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class MfBillingAutoSetting extends Model
{
    protected $table = 'mf_billing_auto_settings';
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'sender_phone' => 'encrypted',
            'first_after_days' => 'integer',
            'repeat_interval_days' => 'integer',
            'max_reminders' => 'integer',
            'templates' => 'encrypted:array',
            'revision' => 'integer',
            'worker_phone' => 'encrypted',
            'worker_connected' => 'boolean',
            'worker_seen_at' => 'datetime',
            'last_generated_at' => 'datetime',
        ];
    }
}
