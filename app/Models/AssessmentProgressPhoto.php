<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentProgressPhoto extends Model
{
    protected $fillable = [
        'assessment_id',
        'position',
        'photo_path',
        'observation',
        'captured_at',
        'uploaded_at',
        'uploaded_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'captured_at' =>
                'datetime',

            'uploaded_at' =>
                'datetime',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(
            Assessment::class
        );
    }
}
