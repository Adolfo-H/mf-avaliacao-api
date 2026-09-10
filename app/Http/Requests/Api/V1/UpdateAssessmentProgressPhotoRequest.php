<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssessmentProgressPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
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
