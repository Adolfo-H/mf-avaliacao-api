<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssessmentNeuromotorTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            /*
             * Flexibilidade
             */
            'flexibility' => [
                'sometimes',
                'array',
            ],

            'flexibility.protocol' => [
                'sometimes',
                'nullable',

                Rule::in([
                    'wells_dillon',
                    'flexitest',
                ]),
            ],

            'flexibility.result' => [
                'sometimes',
                'nullable',
                'numeric',
            ],

            'flexibility.unit' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
            ],

            'flexibility.observation' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],

            'flexibility.test_date' => [
                'sometimes',
                'nullable',
                'date',
            ],

            /*
             * Resistência muscular
             */
            'resistance' => [
                'sometimes',
                'array',
            ],

            'resistance.abdominal_flexion' => [
                'sometimes',
                'array',
            ],

            'resistance.abdominal_flexion.repetitions' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
            ],

            'resistance.abdominal_flexion.time_seconds' => [
                'sometimes',
                'nullable',
                'integer',
                'gt:0',
            ],

            'resistance.abdominal_flexion.protocol' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'resistance.abdominal_flexion.result' => [
                'sometimes',
                'nullable',
                'numeric',
            ],

            'resistance.abdominal_flexion.unit' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
            ],

            'resistance.abdominal_flexion.observation' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],

            'resistance.abdominal_flexion.test_date' => [
                'sometimes',
                'nullable',
                'date',
            ],

            'resistance.push_ups' => [
                'sometimes',
                'array',
            ],

            'resistance.push_ups.repetitions' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
            ],

            'resistance.push_ups.time_seconds' => [
                'sometimes',
                'nullable',
                'integer',
                'gt:0',
            ],

            'resistance.push_ups.protocol' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'resistance.push_ups.result' => [
                'sometimes',
                'nullable',
                'numeric',
            ],

            'resistance.push_ups.unit' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
            ],

            'resistance.push_ups.observation' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],

            'resistance.push_ups.test_date' => [
                'sometimes',
                'nullable',
                'date',
            ],
        ];
    }
}
