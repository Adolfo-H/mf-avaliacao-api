<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UploadStudentPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photo_base64' => [
                'required',
                'string',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'photo_base64.required' =>
                'Selecione uma fotografia.',

            'photo_base64.string' =>
                'A fotografia enviada é inválida.',
        ];
    }
}
