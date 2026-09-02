<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentBodyComposition extends Model
{
    protected $fillable = [
        'assessment_id',
        'payload',
        'results',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',

            'results' => 'encrypted:array',
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
