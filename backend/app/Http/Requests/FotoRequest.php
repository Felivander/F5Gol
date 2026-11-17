<?php

namespace App\Http\Requests;

class FotoCanchaRequest extends ApiRequest
{
    protected function reglas(): array
    {
        return [
            'foto' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'foto.file' => 'La foto tiene que ser un archivo.',
            'foto.image' => 'La foto tiene que ser una imagen.',
            'foto.mimes' => 'La foto tiene que ser jpg, png o webp.',
            'foto.max' => 'La foto no puede pesar más de 4 MB.',
        ]);
    }
}
