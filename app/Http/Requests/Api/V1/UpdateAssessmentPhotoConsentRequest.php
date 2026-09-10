<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssessmentPhotoConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purpose' => [
                'required',
                'string',
                'max:1000',
            ],

            'consent_date' => [
                'required',
                'date',
            ],

            'external_use_allowed' => [
                'required',
                'boolean',
            ],

            'storage_authorized' => [
                'required',
                'boolean',
            ],
        ];
    }
}
