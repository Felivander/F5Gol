<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Support\Carbon;

/**
 * Datos para generar en lote los turnos de una cancha:
 * un rango de fechas, los días de la semana, la franja horaria y la duración de cada turno.
 */
class GenerarTurnosRequest extends ApiRequest
{
    // Para no generar miles de turnos por error
    public const MAXIMO_DIAS = 90;

    protected function reglas(): array
    {
        return [
            'cancha_id' => ['required', 'integer', 'exists:canchas,id'],
            'desde' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde', $this->rangoMaximo()],
            // 0 = domingo ... 6 = sábado (como Carbon::dayOfWeek). Si no viene, todos los días.
            'dias' => ['nullable', 'array', 'min:1'],
            'dias.*' => ['integer', 'between:0,6'],
            'hora_desde' => ['required', 'date_format:H:i'],
            'hora_hasta' => ['required', 'date_format:H:i', $this->despuesDeHoraDesde()],
            'duracion' => ['required', 'integer', 'in:30,60,90,120'],
            'cupo_maximo' => ['nullable', 'integer', 'min:2', 'max:22'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'desde.after_or_equal' => 'La fecha de inicio no puede ser anterior a hoy.',
            'hasta.after_or_equal' => 'La fecha de fin tiene que ser igual o posterior a la de inicio.',
            'desde.date_format' => 'La fecha de inicio tiene que tener el formato AAAA-MM-DD.',
            'hasta.date_format' => 'La fecha de fin tiene que tener el formato AAAA-MM-DD.',
            'hora_desde.date_format' => 'La hora de inicio tiene que tener el formato HH:MM (por ejemplo 15:00).',
            'hora_hasta.date_format' => 'La hora de fin tiene que tener el formato HH:MM (por ejemplo 23:00).',
            'dias.min' => 'Elegí al menos un día de la semana.',
            'dias.*.between' => 'Los días de la semana van de 0 (domingo) a 6 (sábado).',
            'duracion.in' => 'La duración tiene que ser de 30, 60, 90 o 120 minutos.',
        ]);
    }

    private function rangoMaximo(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $desde = $this->input('desde');
            if (! is_string($desde) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value)) {
                return;
            }

            if (Carbon::parse($desde)->diffInDays(Carbon::parse($value)) >= self::MAXIMO_DIAS) {
                $fail('Se pueden generar turnos para un máximo de '.self::MAXIMO_DIAS.' días por vez.');
            }
        };
    }

    private function despuesDeHoraDesde(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $desde = $this->input('hora_desde');

            if (is_string($desde) && is_string($value) && $value <= $desde) {
                $fail('La hora de fin tiene que ser posterior a la hora de inicio.');
            }
        };
    }
}
