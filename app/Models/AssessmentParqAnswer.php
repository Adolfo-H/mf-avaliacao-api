<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentParqAnswer extends Model
{
    protected $fillable = [
        'assessment_id',
        'question_version_id',
        'answer',
        'answered_by',
    ];

    protected function casts(): array
    {
        return [
            'answer' => 'boolean',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(
            Assessment::class
        );
    }

    public function questionVersion(): BelongsTo
    {
        return $this->belongsTo(
            ParqQuestionVersion::class,
            'question_version_id'
        );
    }

    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'answered_by'
        );
    }
}
