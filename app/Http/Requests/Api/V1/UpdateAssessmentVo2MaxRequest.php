<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssessmentVo2MaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'activity_level' => [
                'sometimes',
                'nullable',

                Rule::in([
                    'active',
                    'sedentary',
                ]),
            ],

            'protocol' => [
                'sometimes',
                'nullable',

                Rule::in([
                    'cooper_12_min',
                    'run_2400m',
                    'walk_1_mile',
                ]),
            ],

            'test' => [
                'sometimes',
                'array',
            ],

            'test.distance_m' => [
                'sometimes',
                'nullable',
                'numeric',
                'gt:0',
            ],

            'test.time_seconds' => [
                'sometimes',
                'nullable',
                'integer',
                'gt:0',
            ],

            'test.heart_rate_bpm' => [
                'sometimes',
                'nullable',
                'integer',
                'gt:0',
            ],

            'test.weight_kg' => [
                'sometimes',
                'nullable',
                'numeric',
                'gt:0',
            ],

            'test.conditions' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],

            'test.observations' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],

            'test.test_date' => [
                'sometimes',
                'nullable',
                'date',
            ],
        ];
    }
}
