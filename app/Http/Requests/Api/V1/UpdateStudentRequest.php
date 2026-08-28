<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:180',
            ],

            'birth_date' => [
                'sometimes',
                'nullable',
                'date',
                'before_or_equal:today',
            ],

            'sex' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in([
                    'male',
                    'female',
                    'other',
                    'not_informed',
                ]),
            ],

            'address' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'address_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
            ],

            'address_complement' => [
                'sometimes',
                'nullable',
                'string',
                'max:120',
            ],

            'neighborhood' => [
                'sometimes',
                'nullable',
                'string',
                'max:120',
            ],

            'city' => [
                'sometimes',
                'nullable',
                'string',
                'max:120',
            ],

            'state' => [
                'sometimes',
                'nullable',
                'string',
                'size:2',
            ],

            'mobile_phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
            ],

            'home_phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
            ],

            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
            ],

            'administrative_notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }
}
