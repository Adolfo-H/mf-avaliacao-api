<?php

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEvaluatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $evaluator = $this->route('evaluator');

        $evaluatorId = $evaluator instanceof User
            ? $evaluator->id
            : $evaluator;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:180',
            ],

            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($evaluatorId),
            ],

            'password' => [
                'nullable',
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
        ];
    }
}
