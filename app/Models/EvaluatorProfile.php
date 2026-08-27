<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluatorProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'phone',
        'professional_registration',
        'specialty',
        'photo_path',
        'signature_path',
        'company_name',
        'company_logo_path',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
