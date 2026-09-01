<?php

namespace App\Models;

use App\Enums\AssessmentSectionStatus;
use App\Enums\AssessmentSectionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentSection extends Model
{
    protected $fillable = [
        'assessment_id',
        'section',
        'status',
        'started_at',
        'completed_at',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'section' => AssessmentSectionType::class,

            'status' => AssessmentSectionStatus::class,

            'started_at' => 'datetime',

            'completed_at' => 'datetime',
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

    public function changeStatus(
        AssessmentSectionStatus $status,
        int $userId
    ): void {
        $payload = [
            'status' => $status,

            'updated_by' => $userId,
        ];

        if (
            $status !==
            AssessmentSectionStatus::NotStarted
            &&
            $this->started_at === null
        ) {
            $payload['started_at'] =
                now();
        }

        if (
            $status ===
            AssessmentSectionStatus::Completed
        ) {
            $payload['completed_at'] =
                now();
        } else {
            $payload['completed_at'] =
                null;
        }

        $this->update($payload);
    }
}
