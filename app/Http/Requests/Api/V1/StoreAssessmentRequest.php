<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_uuid' => [
                'required',
                'uuid',
                Rule::exists('students', 'uuid')
                    ->whereNull('archived_at'),
            ],

            'evaluator_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')
                    ->where('active', true)
                    ->where('role', 'evaluator'),
            ],

            'evaluation_date' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:today',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'student_uuid.required' => 'Selecione o aluno.',

            'student_uuid.uuid' => 'O aluno informado é inválido.',

            'student_uuid.exists' => 'O aluno informado não está disponível.',

            'evaluator_id.required' => 'Selecione o avaliador.',

            'evaluator_id.exists' => 'O avaliador informado não está disponível.',

            'evaluation_date.required' => 'Informe a data da avaliação.',

            'evaluation_date.date_format' => 'A data da avaliação deve estar no formato AAAA-MM-DD.',

            'evaluation_date.before_or_equal' => 'A data da avaliação não pode ser futura.',
        ];
    }
}
