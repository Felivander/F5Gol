<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base de las validaciones de la API.
 *
 * Un mismo request sirve para crear (POST) y para editar (PUT/PATCH):
 * al editar, cada campo es opcional, pero si se manda tiene que ser válido.
 * Si algo falla, Laravel responde 422 con los mensajes en español de lang/es/validation.php
 * (o los propios de cada request en messages()).
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

    /**
     * Mensajes propios de cada request. Los generales (obligatorio, formato, máximo, etc.)
     * y los nombres legibles de los campos están en lang/es/validation.php.
     */
    public function messages(): array
    {
        return [];
    }
}
