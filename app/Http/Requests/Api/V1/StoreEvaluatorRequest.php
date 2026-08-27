<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreEvaluatorRequest extends FormRequest
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

            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
                'max:255',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'professional_registration' => [
                'nullable',
                'string',
                'max:100',
            ],

            'specialty' => [
                'nullable',
                'string',
                'max:160',
            ],

            'company_name' => [
                'nullable',
                'string',
                'max:180',
            ],

            'active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
