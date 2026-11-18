<?php

namespace App\Http\Requests;

use App\Models\Cancha;
use Illuminate\Validation\Rule;

class CanchaRequest extends ApiRequest
{
    protected function reglas(): array
    {
        return [
            'club_id' => ['required', 'integer', 'exists:clubs,id'],
            'nombre' => ['required', 'string', 'max:100'],
            'tipo' => ['nullable', 'string', 'max:20'],
            'capacidad_jugadores' => ['nullable', 'integer', 'min:2', 'max:22'],
            'superficie' => ['nullable', Rule::in(Cancha::SUPERFICIES)],
            'techada' => ['nullable', 'boolean'],
            'iluminacion' => ['nullable', 'boolean'],
            'vestuarios' => ['nullable', 'boolean'],
            'descripcion' => ['nullable', 'string'],
            'tarifa' => ['required', 'numeric', 'min:0'],
            'estado' => ['nullable', 'in:activa,inactiva'],
        ];
    }
}
