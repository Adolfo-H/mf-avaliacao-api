<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentPhotoConsent extends Model
{
    protected $fillable = [
        'assessment_id',
        'purpose',
        'consent_date',
        'external_use_allowed',
        'storage_authorized',
        'revoked_at',
        'registered_by',
        'updated_by',
        'revoked_by',
    ];

    protected function casts(): array
    {
        return [
            'consent_date' =>
                'date',

            'external_use_allowed' =>
                'boolean',

            'storage_authorized' =>
                'boolean',

            'revoked_at' =>
                'datetime',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(
            Assessment::class
        );
    }

    public function isActive(): bool
    {
        return
            $this->storage_authorized &&
            $this->revoked_at === null;
    }
}
