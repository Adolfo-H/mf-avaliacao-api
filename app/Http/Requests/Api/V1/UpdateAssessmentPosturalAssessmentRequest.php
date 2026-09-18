<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssessmentPosturalAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lateral' => [
                'sometimes',
                'array',
            ],

            'lateral.alterations' => [
                'sometimes',
                'array',
            ],

            'lateral.alterations.*' => [
                'string',
                'distinct',

                Rule::in([
                    'hip_anteversion',
                    'hip_retroversion',
                    'internal_shoulder_rotation',
                    'cervical_straightening',
                    'lumbar_straightening',
                    'abdominal_protrusion',
                    'cervical_hyperlordosis',
                    'lumbar_hyperlordosis',
                    'thoracic_hyperkyphosis',
                    'flat_foot',
                    'high_arch_foot',
                    'calcaneus_foot',
                    'equinus_foot',
                    'flexed_knee',
                    'recurvatum_knee',
                ]),
            ],

            'posterior' => [
                'sometimes',
                'array',
            ],

            'posterior.alterations' => [
                'sometimes',
                'array',
            ],

            'posterior.alterations.*' => [
                'string',
                'distinct',

                Rule::in([
                    'cervical_scoliosis',
                    'thoracic_scoliosis',
                    'lumbar_scoliosis',
                    'scapular_protraction',
                    'scapular_retraction',
                    'scapular_depression',
                    'valgus_foot',
                    'varus_foot',
                    'trapezius_shortening',
                ]),
            ],

            'anterior' => [
                'sometimes',
                'array',
            ],

            'anterior.alterations' => [
                'sometimes',
                'array',
            ],

            'anterior.alterations.*' => [
                'string',
                'distinct',

                Rule::in([
                    'genu_valgum',
                    'genu_varum',
                    'adducted_foot',
                    'abducted_foot',
                ]),
            ],

            'anterior.shoulder_asymmetry' => [
                'sometimes',
                'nullable',

                Rule::in([
                    'none',
                    'right_elevated',
                    'left_elevated',
                ]),
            ],

            'anterior.hip_asymmetry' => [
                'sometimes',
                'nullable',

                Rule::in([
                    'none',
                    'right_elevated',
                    'left_elevated',
                ]),
            ],

            'observations' => [
                'sometimes',
                'nullable',
                'string',
                'max:10000',
            ],
        ];
    }
}
