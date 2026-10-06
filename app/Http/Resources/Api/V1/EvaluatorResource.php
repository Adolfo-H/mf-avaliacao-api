<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvaluatorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'name' => $this->name,

            'email' => $this->email,

            'role' => $this->role instanceof UserRole
                ? $this->role->value
                : $this->role,

            'active' => (bool) $this->active,

            'profile' => $this->whenLoaded(
                'evaluatorProfile',
                function (): ?array {
                    $profile =
                        $this->evaluatorProfile;

                    if (! $profile) {
                        return null;
                    }

                    return [
                        'phone' =>
                            $profile->phone,

                        'professional_registration' =>
                            $profile
                                ->professional_registration,

                        'specialty' =>
                            $profile->specialty,

                        'company_name' =>
                            $profile->company_name,

                        'photo_path' =>
                            $profile->photo_path,

                        'signature_path' =>
                            $profile->signature_path,

                        'company_logo_path' =>
                            $profile
                                ->company_logo_path,
                    ];
                }
            ),

            'created_at' =>
                $this->created_at?->toISOString(),

            'updated_at' =>
                $this->updated_at?->toISOString(),
        ];
    }
}
