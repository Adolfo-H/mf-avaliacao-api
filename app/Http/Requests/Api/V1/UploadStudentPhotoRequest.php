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
            'photo' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:'.config(
                    'student-photos.max_size_kb',
                    5120
                ),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.required' => 'Selecione uma fotografia.',

            'photo.file' => 'A fotografia enviada é inválida.',

            'photo.image' => 'O arquivo precisa ser uma imagem válida.',

            'photo.mimes' => 'A fotografia deve estar em JPG, PNG ou WEBP.',

            'photo.max' => 'A fotografia pode possuir no máximo 5 MB.',
        ];
    }
}
