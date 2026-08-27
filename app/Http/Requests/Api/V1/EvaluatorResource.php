<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvaluatorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->evaluatorProfile;

        return [
            'id' => $this->id,

            'name' => $this->name,

            'email' => $this->email,

            'role' => $this->role->value,

            'active' => $this->active,

            'profile' => [
                'phone' => $profile?->phone,

                'professional_registration' => $profile?->professional_registration,

                'specialty' => $profile?->specialty,

                'company_name' => $profile?->company_name,

                'photo_path' => $profile?->photo_path,

                'signature_path' => $profile?->signature_path,

                'company_logo_path' => $profile?->company_logo_path,
            ],

            'created_at' => $this->created_at?->toIso8601String(),

            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
