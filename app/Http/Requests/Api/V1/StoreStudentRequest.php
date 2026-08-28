<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:180',
            ],

            'birth_date' => [
                'nullable',
                'date',
                'before_or_equal:today',
            ],

            'sex' => [
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
                'nullable',
                'string',
                'max:255',
            ],

            'address_number' => [
                'nullable',
                'string',
                'max:30',
            ],

            'address_complement' => [
                'nullable',
                'string',
                'max:120',
            ],

            'neighborhood' => [
                'nullable',
                'string',
                'max:120',
            ],

            'city' => [
                'nullable',
                'string',
                'max:120',
            ],

            'state' => [
                'nullable',
                'string',
                'size:2',
            ],

            'mobile_phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'home_phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'active' => [
                'sometimes',
                'boolean',
            ],

            'administrative_notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }
}
