<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentAnamnesis extends Model
{
    protected $fillable = [
        'assessment_id',
        'payload',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            /*
             * O Laravel criptografa o JSON
             * completo antes de gravá-lo.
             */
            'payload' => 'encrypted:array',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(
            Assessment::class
        );
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }
}
