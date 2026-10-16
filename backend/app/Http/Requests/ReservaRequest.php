<?php

namespace App\Http\Requests;

class ReservaRequest extends ApiRequest
{
    protected function reglas(): array
    {
        return [
            'turno_id' => ['required', 'integer', 'exists:turnos,id'],
            'usuario_id' => ['required', 'integer', 'exists:users,id'],
            'tipo' => ['nullable', 'in:completa,individual'],
            'cantidad_jugadores' => ['nullable', 'integer', 'min:1', 'max:22'],
            'estado' => ['nullable', 'in:pendiente,confirmada,cancelada'],
            'estado_pago' => ['nullable', 'in:pendiente,sena,pagado'],
        ];
    }
}
