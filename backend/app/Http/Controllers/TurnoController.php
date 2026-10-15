<?php

namespace App\Http\Controllers;

use App\Models\Turno;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class TurnoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * Grilla de disponibilidad: /api/turnos?cancha_id=1&fecha=2026-09-10
     */
    #[OA\Get(
        path: '/api/turnos',
        summary: 'Listar turnos',
        tags: ['Turnos'],
        parameters: [
            new OA\Parameter(name: 'cancha_id', description: 'Filtrar por cancha', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'fecha', description: 'Filtrar por fecha (YYYY-MM-DD)', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Listado obtenido correctamente.'),
        ]
    )]
    public function index(Request $request)
    {
        $query = Turno::with('cancha:id,nombre,club_id')->orderBy('hora_inicio');

        if ($request->has('cancha_id')) {
            $query->where('cancha_id', $request->query('cancha_id'));
        }

        if ($request->has('fecha')) {
            $query->where('fecha', $request->query('fecha'));
        }

        $turnos = $query->get()->map(function (Turno $turno) {
            $turno->cupo_actual = $turno->cupoActual();

            return $turno;
        });

        return response()->json($turnos, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    #[OA\Post(
        path: '/api/turnos',
        summary: 'Crear turno',
        tags: ['Turnos'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/TurnoInput')),
        responses: [
            new OA\Response(response: 201, description: 'Creado correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
        ]
    )]
    public function store(Request $request)
    {
        $turno = Turno::create($request->all());

        return response()->json([
            'message' => 'Turno creado correctamente',
            'turno' => $turno,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    #[OA\Get(
        path: '/api/turnos/{id}',
        summary: 'Obtener turno',
        tags: ['Turnos'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/id')],
        responses: [
            new OA\Response(response: 200, description: 'Operación exitosa.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
        ]
    )]
    public function show(string $id)
    {
        $turno = Turno::with('cancha')->findOrFail($id);
        $turno->cupo_actual = $turno->cupoActual();

        return response()->json($turno, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    #[OA\Put(
        path: '/api/turnos/{id}',
        summary: 'Actualizar turno',
        tags: ['Turnos'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/id')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/TurnoInput')),
        responses: [
            new OA\Response(response: 200, description: 'Actualizado correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
        ]
    )]
    public function update(Request $request, string $id)
    {
        $turno = Turno::find($id);

        if (! $turno) {
            return response()->json([
                'message' => 'Turno no encontrado',
            ], 404);
        }

        $turno->update($request->all());

        return response()->json([
            'message' => 'Turno actualizado correctamente',
            'turno' => $turno->fresh(),
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    #[OA\Delete(
        path: '/api/turnos/{id}',
        summary: 'Eliminar turno',
        tags: ['Turnos'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/id')],
        responses: [
            new OA\Response(response: 200, description: 'Eliminado correctamente.', content: new OA\JsonContent(ref: '#/components/schemas/Mensaje')),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
        ]
    )]
    public function destroy(string $id)
    {
        $turno = Turno::find($id);

        if (! $turno) {
            return response()->json([
                'message' => 'Turno no encontrado',
            ], 404);
        }

        $turno->delete();

        return response()->json([
            'message' => 'Turno eliminado correctamente',
        ], 200);
    }
}
