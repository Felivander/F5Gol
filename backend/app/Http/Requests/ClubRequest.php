<?php

namespace App\Http\Requests;

class ClubRequest extends ApiRequest
{
    // El dueño no se manda: es siempre el administrador logueado (lo asigna ClubController)
    protected function reglas(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:150'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]*$/'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'telefono.regex' => 'El teléfono solo puede tener números, espacios, +, - y paréntesis.',
        ]);
    }
}
