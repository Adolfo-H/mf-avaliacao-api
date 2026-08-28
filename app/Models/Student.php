<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'photo_path',
        'name',
        'birth_date',
        'sex',
        'address',
        'address_number',
        'address_complement',
        'neighborhood',
        'city',
        'state',
        'mobile_phone',
        'home_phone',
        'email',
        'active',
        'administrative_notes',
        'archived_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'active' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Student $student): void {
            if (! $student->uuid) {
                $student->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by',
        );
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by',
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('active', true)
            ->whereNull('archived_at');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function currentAge(): ?int
    {
        return $this->ageAt(now());
    }

    public function ageAt(
        CarbonInterface|string|null $date = null,
    ): ?int {
        if (! $this->birth_date) {
            return null;
        }

        $referenceDate = match (true) {
            $date instanceof CarbonInterface => $date,
            is_string($date) => Carbon::parse($date),
            default => now(),
        };

        if (
            $referenceDate->lt(
                $this->birth_date,
            )
        ) {
            return null;
        }

        return $this
            ->birth_date
            ->diffInYears($referenceDate);
    }
}
