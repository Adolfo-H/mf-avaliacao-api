<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssessmentAnthropometryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $measurement = [
            'sometimes',
            'nullable',
            'numeric',
            'gt:0',
            'max:999.99',
        ];

        return [
            /*
             * Perímetros em centímetros.
             */
            'circumferences' => [
                'sometimes',
                'array',
            ],

            /*
             * Medidas bilaterais.
             */
            'circumferences.forearm' => [
                'sometimes',
                'array',
            ],

            'circumferences.forearm.right' =>
                $measurement,

            'circumferences.forearm.left' =>
                $measurement,

            'circumferences.relaxed_arm' => [
                'sometimes',
                'array',
            ],

            'circumferences.relaxed_arm.right' =>
                $measurement,

            'circumferences.relaxed_arm.left' =>
                $measurement,

            'circumferences.flexed_arm' => [
                'sometimes',
                'array',
            ],

            'circumferences.flexed_arm.right' =>
                $measurement,

            'circumferences.flexed_arm.left' =>
                $measurement,

            'circumferences.proximal_thigh' => [
                'sometimes',
                'array',
            ],

            'circumferences.proximal_thigh.right' =>
                $measurement,

            'circumferences.proximal_thigh.left' =>
                $measurement,

            'circumferences.medial_thigh' => [
                'sometimes',
                'array',
            ],

            'circumferences.medial_thigh.right' =>
                $measurement,

            'circumferences.medial_thigh.left' =>
                $measurement,

            'circumferences.distal_thigh' => [
                'sometimes',
                'array',
            ],

            'circumferences.distal_thigh.right' =>
                $measurement,

            'circumferences.distal_thigh.left' =>
                $measurement,

            'circumferences.calf' => [
                'sometimes',
                'array',
            ],

            'circumferences.calf.right' =>
                $measurement,

            'circumferences.calf.left' =>
                $measurement,

            /*
             * Medidas centrais.
             */
            'circumferences.abdomen' =>
                $measurement,

            'circumferences.waist' =>
                $measurement,

            'circumferences.shoulder' =>
                $measurement,

            'circumferences.hip' =>
                $measurement,

            'circumferences.chest' =>
                $measurement,

            'circumferences.neck' =>
                $measurement,

            /*
             * Diâmetros ósseos em centímetros.
             */
            'bone_diameters' => [
                'sometimes',
                'array',
            ],

            'bone_diameters.wrist' =>
                $measurement,

            'bone_diameters.humerus' =>
                $measurement,

            'bone_diameters.femur' =>
                $measurement,
        ];
    }

    public function messages(): array
    {
        return [
            '*.numeric' =>
                'As medidas devem possuir valores numéricos.',

            '*.gt' =>
                'As medidas devem ser maiores que zero.',

            '*.max' =>
                'O valor informado para a medida é inválido.',
        ];
    }
}
