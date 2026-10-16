<?php

namespace App\Http\Requests;

class CanchaRequest extends ApiRequest
{
    protected function reglas(): array
    {
        return [
            'club_id' => ['required', 'integer', 'exists:clubs,id'],
            'nombre' => ['required', 'string', 'max:100'],
            'tipo' => ['nullable', 'string', 'max:20'],
            'capacidad_jugadores' => ['nullable', 'integer', 'min:2', 'max:22'],
            'descripcion' => ['nullable', 'string'],
            'tarifa' => ['required', 'numeric', 'min:0'],
            'estado' => ['nullable', 'in:activa,inactiva'],
        ];
    }
}
