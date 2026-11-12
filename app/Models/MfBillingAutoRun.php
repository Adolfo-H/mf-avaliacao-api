<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class MfBillingAutoRun extends Model
{
    protected $table = 'mf_billing_auto_runs';
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sent_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
