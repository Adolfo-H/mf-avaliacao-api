<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_uuid' => [
                'sometimes',
                'required',
                'uuid',
                Rule::exists('students', 'uuid')
                    ->whereNull('archived_at'),
            ],

            'evaluator_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('users', 'id')
                    ->where('active', true)
                    ->where('role', 'evaluator'),
            ],

            'evaluation_date' => [
                'sometimes',
                'required',
                'date_format:Y-m-d',
                'before_or_equal:today',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'student_uuid.exists' => 'O aluno informado não está disponível.',

            'evaluator_id.exists' => 'O avaliador informado não está disponível.',

            'evaluation_date.date_format' => 'A data da avaliação deve estar no formato AAAA-MM-DD.',

            'evaluation_date.before_or_equal' => 'A data da avaliação não pode ser futura.',
        ];
    }
}
