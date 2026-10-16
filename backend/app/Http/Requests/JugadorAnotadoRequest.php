<?php

namespace App\Http\Requests;

use App\Models\JugadorAnotado;
use Closure;

class JugadorAnotadoRequest extends ApiRequest
{
    protected function reglas(): array
    {
        return [
            'reserva_id' => ['required', 'integer', 'exists:reservas,id'],
            'usuario_id' => ['required', 'integer', 'exists:users,id', $this->noAnotadoDosVeces()],
            'fecha_inscripcion' => ['nullable', 'date'],
        ];
    }

    private function noAnotadoDosVeces(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $actual = $this->esEdicion() ? JugadorAnotado::find($this->route('jugador_anotado')) : null;

            $existe = JugadorAnotado::where('reserva_id', $this->input('reserva_id', $actual?->reserva_id))
                ->where('usuario_id', $value)
                ->when($actual, fn ($q) => $q->where('id', '!=', $actual->id))
                ->exists();

            if ($existe) {
                $fail('Ese usuario ya está anotado en esta reserva.');
            }
        };
    }
}
