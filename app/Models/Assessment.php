<?php

namespace App\Models;

use App\Enums\AssessmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Assessment extends Model
{
    protected $fillable = [
        'student_id',
        'evaluator_id',
        'evaluation_date',
        'status',
        'completed_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'evaluation_date' => 'date',

            'status' => AssessmentStatus::class,

            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(
            function (Assessment $assessment): void {
                if (! $assessment->uuid) {
                    $assessment->uuid =
                        (string) Str::uuid();
                }
            }
        );
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(
            Student::class
        );
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'evaluator_id'
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }

    public function scopeDraft(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            AssessmentStatus::Draft->value
        );
    }

    public function scopeCompleted(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            AssessmentStatus::Completed->value
        );
    }

    public function isDraft(): bool
    {
        return $this->status ===
            AssessmentStatus::Draft;
    }

    public function isCompleted(): bool
    {
        return $this->status ===
            AssessmentStatus::Completed;
    }

    public function ageAtEvaluation(): ?int
    {
        if (! $this->student) {
            return null;
        }

        return $this->student->ageAt(
            $this->evaluation_date
        );
    }
}
