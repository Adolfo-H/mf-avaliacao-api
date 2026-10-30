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
             * Transcrição do equipamento de bioimpedância.
             * NÃO são cálculos nem classificações clínicas homologados.
             */
            'bioimpedance' => ['sometimes', 'nullable', 'array:manufacturer,model,software_version,measured_on,reported_weight_kg,body_fat_percentage,fat_mass_kg,fat_free_mass_kg,skeletal_muscle_mass_kg,total_body_water_percentage,resistance_ohm,reactance_ohm,conditions,notes'],
            'bioimpedance.manufacturer' => ['sometimes', 'nullable', 'string', 'max:120'],
            'bioimpedance.model' => ['sometimes', 'nullable', 'string', 'max:120'],
            'bioimpedance.software_version' => ['sometimes', 'nullable', 'string', 'max:120'],
            'bioimpedance.measured_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'bioimpedance.reported_weight_kg' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:500'],
            'bioimpedance.body_fat_percentage' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'bioimpedance.fat_mass_kg' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:500'],
            'bioimpedance.fat_free_mass_kg' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:500'],
            'bioimpedance.skeletal_muscle_mass_kg' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:500'],
            'bioimpedance.total_body_water_percentage' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'bioimpedance.resistance_ohm' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:5000'],
            'bioimpedance.reactance_ohm' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:5000'],
            'bioimpedance.conditions' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'bioimpedance.notes' => ['sometimes', 'nullable', 'string', 'max:2000'],

            /*
             * Weltman: duas medidas abdominais independentes da cintura.
             * Gravação no payload criptografado; resultados NÃO homologados.
             */
            'weltman' => ['sometimes', 'nullable', 'array:abdomen_upper_cm,abdomen_umbilicus_cm,applicability_confirmed'],
            'weltman.abdomen_upper_cm' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:300'],
            'weltman.abdomen_umbilicus_cm' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:300'],
            'weltman.applicability_confirmed' => ['sometimes', 'boolean'],

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
