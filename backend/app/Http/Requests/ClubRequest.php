<?php

namespace App\Http\Requests;

class ClubRequest extends ApiRequest
{
    protected function reglas(): array
    {
        return [
            'administrador_id' => ['required', 'integer', 'exists:users,id'],
            'nombre' => ['required', 'string', 'max:150'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
        ];
    }
}
