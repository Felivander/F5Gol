<?php

namespace App\Http\Requests;

use App\Models\Turno;
use Closure;

class TurnoRequest extends ApiRequest
{
    protected function reglas(): array
    {
        return [
            'cancha_id' => ['required', 'integer', 'exists:canchas,id'],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'hora_inicio' => ['required', 'date_format:H:i,H:i:s', $this->noRepetido()],
            'hora_fin' => ['required', 'date_format:H:i,H:i:s', $this->despuesDelInicio()],
            'estado' => ['nullable', 'in:disponible,ocupado,bloqueado'],
            'cupo_maximo' => ['nullable', 'integer', 'min:1', 'max:22'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'fecha.date_format' => 'La fecha tiene que tener el formato AAAA-MM-DD (por ejemplo 2026-12-10).',
            'hora_inicio.date_format' => 'La hora de inicio tiene que tener el formato HH:MM (por ejemplo 20:00).',
            'hora_fin.date_format' => 'La hora de fin tiene que tener el formato HH:MM (por ejemplo 21:00).',
        ]);
    }

    // Al editar, los campos que no vienen se toman del turno guardado
    private function valor(string $campo): mixed
    {
        $actual = $this->esEdicion() ? Turno::find($this->route('turno')) : null;

        return $this->input($campo, $actual?->{$campo});
    }

    private function noRepetido(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $existe = Turno::where('cancha_id', $this->valor('cancha_id'))
                ->where('fecha', $this->valor('fecha'))
                ->where('hora_inicio', substr($value, 0, 5))
                ->when($this->esEdicion(), fn ($q) => $q->where('id', '!=', $this->route('turno')))
                ->exists();

            if ($existe) {
                $fail('Ya existe un turno en esa cancha para esa fecha y hora de inicio.');
            }
        };
    }

    private function despuesDelInicio(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $inicio = $this->valor('hora_inicio');

            if ($inicio && substr($value, 0, 5) <= substr($inicio, 0, 5)) {
                $fail('La hora de fin tiene que ser posterior a la hora de inicio.');
            }
        };
    }
}
