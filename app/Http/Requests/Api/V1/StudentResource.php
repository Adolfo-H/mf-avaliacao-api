<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,

            'photo_path' => $this->photo_path,

            'name' => $this->name,

            'birth_date' => $this->birth_date?->format('Y-m-d'),

            'age' => $this->currentAge(),

            'sex' => $this->sex,

            'address' => [
                'street' => $this->address,
                'number' => $this->address_number,
                'complement' => $this->address_complement,
                'neighborhood' => $this->neighborhood,
                'city' => $this->city,
                'state' => $this->state,
            ],

            'contact' => [
                'mobile_phone' => $this->mobile_phone,
                'home_phone' => $this->home_phone,
                'email' => $this->email,
            ],

            'active' => $this->active,

            'archived' => $this->isArchived(),

            'administrative_notes' => $this->administrative_notes,

            'created_at' => $this->created_at?->toIso8601String(),

            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
