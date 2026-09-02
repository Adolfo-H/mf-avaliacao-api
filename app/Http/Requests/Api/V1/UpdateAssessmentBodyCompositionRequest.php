<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssessmentBodyCompositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'protocol' => [
                'sometimes',
                'nullable',
                'string',

                Rule::in([
                    'pollock_7',
                    'pollock_3',
                    'guedes_3',
                    'bioimpedance',
                    'weltman',
                ]),
            ],

            /*
             * Medidas básicas.
             */
            'weight_kg' => [
                'sometimes',
                'nullable',
                'numeric',
                'gt:0',
                'max:999.99',
            ],

            'height_m' => [
                'sometimes',
                'nullable',
                'numeric',
                'gt:0',
                'max:5',
            ],

            'target_body_fat_percentage' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],

            /*
             * Dobras cutâneas em milímetros.
             */
            'skinfolds' => [
                'sometimes',
                'array',
            ],

            'skinfolds.subscapular' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:999',
            ],

            'skinfolds.chest' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:999',
            ],

            'skinfolds.suprailiac' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:999',
            ],

            'skinfolds.thigh' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:999',
            ],

            'skinfolds.triceps' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:999',
            ],

            'skinfolds.midaxillary' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:999',
            ],

            'skinfolds.abdominal' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:999',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'protocol.in' => 'O protocolo informado não é válido.',

            'weight_kg.gt' => 'O peso deve ser maior que zero.',

            'height_m.gt' => 'A altura deve ser maior que zero.',

            'target_body_fat_percentage.min' => 'O percentual de gordura desejado não pode ser negativo.',

            'target_body_fat_percentage.max' => 'O percentual de gordura desejado não pode ultrapassar 100%.',

            'skinfolds.*.min' => 'Os valores das dobras não podem ser negativos.',
        ];
    }
}
