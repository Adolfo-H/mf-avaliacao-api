<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentPosturalPhoto extends Model
{
    protected $fillable = [
        'assessment_id',
        'position',
        'photo_path',
        'grid_enabled',
        'grid_settings',
        'observation',
        'captured_at',
        'uploaded_at',
        'uploaded_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'grid_enabled' =>
                'boolean',

            'grid_settings' =>
                'encrypted:array',

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

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'uploaded_by'
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
