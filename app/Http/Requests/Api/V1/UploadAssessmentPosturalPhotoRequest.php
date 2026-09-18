<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UploadAssessmentPosturalPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photo_base64' => [
                'required',
                'string',
            ],

            'grid_enabled' => [
                'sometimes',
                'boolean',
            ],

            'grid_settings' => [
                'sometimes',
                'nullable',
                'array',
            ],

            'observation' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],

            'captured_at' => [
                'sometimes',
                'nullable',
                'date',
            ],
        ];
    }
}
