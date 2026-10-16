<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base de las validaciones de la API.
 *
 * Un mismo request sirve para crear (POST) y para editar (PUT/PATCH):
 * al editar, cada campo es opcional, pero si se manda tiene que ser válido.
 * Si algo falla, Laravel responde 422 con los mensajes en español de messages().
 */
abstract class ApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La autenticación la controla el middleware auth:sanctum de las rutas
        return true;
    }

    protected function esEdicion(): bool
    {
        return $this->isMethod('PUT') || $this->isMethod('PATCH');
    }

    /**
     * Reglas para crear; al editar se les antepone "sometimes"
     * para que solo se validen los campos que vienen en el pedido.
     *
     * @return array<string, array<int, mixed>>
     */
    abstract protected function reglas(): array;

    public function rules(): array
    {
        $reglas = $this->reglas();

        if ($this->esEdicion()) {
            foreach ($reglas as $campo => $lista) {
                $reglas[$campo] = array_merge(['sometimes'], $lista);
            }
        }

        return $reglas;
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute tiene que ser un texto.',
            'integer' => 'El campo :attribute tiene que ser un número entero.',
            'numeric' => 'El campo :attribute tiene que ser un número.',
            'max' => 'El campo :attribute supera el máximo permitido (:max).',
            'min' => 'El campo :attribute tiene que ser como mínimo :min.',
            'exists' => 'El :attribute indicado no existe.',
            'in' => 'El campo :attribute tiene que ser uno de estos valores: :values.',
            'date' => 'El campo :attribute tiene que ser una fecha válida.',
            'date_format' => 'El campo :attribute tiene que tener el formato :format.',
            'after' => 'El campo :attribute tiene que ser posterior a :date.',
        ];
    }

    public function attributes(): array
    {
        return [
            'administrador_id' => 'administrador',
            'club_id' => 'club',
            'cancha_id' => 'cancha',
            'turno_id' => 'turno',
            'reserva_id' => 'reserva',
            'usuario_id' => 'usuario',
            'capacidad_jugadores' => 'capacidad de jugadores',
            'cupo_maximo' => 'cupo máximo',
            'cantidad_jugadores' => 'cantidad de jugadores',
            'hora_inicio' => 'hora de inicio',
            'hora_fin' => 'hora de fin',
            'estado_pago' => 'estado de pago',
            'fecha_inscripcion' => 'fecha de inscripción',
            'direccion' => 'dirección',
            'telefono' => 'teléfono',
            'descripcion' => 'descripción',
        ];
    }
}
