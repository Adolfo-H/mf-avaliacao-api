<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ParqQuestionVersion extends Model
{
    protected $fillable = [
        'question_key',
        'version',
        'position',
        'question_text',
        'active',
        'approved_at',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',

            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(
            function (
                ParqQuestionVersion $question
            ): void {
                if (! $question->uuid) {
                    $question->uuid =
                        (string) Str::uuid();
                }
            }
        );
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'approved_by'
        );
    }

    public function answers(): HasMany
    {
        return $this->hasMany(
            AssessmentParqAnswer::class,
            'question_version_id'
        );
    }
}
