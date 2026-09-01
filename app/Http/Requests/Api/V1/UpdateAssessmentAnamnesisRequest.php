<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssessmentAnamnesisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            /*
             * Objetivos
             */
            'objectives' => [
                'sometimes',
                'array',
                'max:7',
            ],

            'objectives.*' => [
                'string',
                Rule::in([
                    'muscle_mass',
                    'aerobic_capacity',
                    'health_quality_of_life',
                    'muscle_strengthening',
                    'general_conditioning',
                    'weight_loss',
                    'other',
                ]),
            ],

            'objective_other' => [
                'sometimes',
                'nullable',
                'string',
                'max:500',
            ],

            /*
             * Rotina de exercícios
             */
            'exercises_regularly' => [
                'sometimes',
                'nullable',
                'boolean',
            ],

            'exercise_activity' => [
                'sometimes',
                'nullable',
                'string',
                'max:500',
            ],

            'exercise_frequency_per_week' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
                'max:50',
            ],

            'exercise_duration_minutes' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
                'max:1440',
            ],

            /*
             * Dores
             */
            'spine_pain_regions' => [
                'sometimes',
                'array',
                'max:3',
            ],

            'spine_pain_regions.*' => [
                'string',
                Rule::in([
                    'thoracic',
                    'lumbar',
                    'cervical',
                ]),
            ],

            'joint_limitations' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],

            /*
             * Histórico
             */
            'recent_surgery' => [
                'sometimes',
                'nullable',
                'boolean',
            ],

            'surgery_type' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],

            'surgery_date' => [
                'sometimes',
                'nullable',
                'date_format:Y-m-d',
                'before_or_equal:today',
            ],

            'medications' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],

            'health_problems' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],

            'clinical_notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],

            /*
             * Sinais vitais
             */
            'resting_heart_rate' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                'max:999',
            ],

            'systolic_blood_pressure' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                'max:999',
            ],

            'diastolic_blood_pressure' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                'max:999',
            ],

            /*
             * PAR-Q
             */
            'parq_answers' => [
                'sometimes',
                'array',
                'max:7',
            ],

            'parq_answers.*.question_version_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists(
                    'parq_question_versions',
                    'id'
                ),
            ],

            'parq_answers.*.answer' => [
                'required',
                'boolean',
            ],
        ];
    }
}
