<?php

namespace App\Http\Controllers;

use App\Models\Turno;
use App\Http\Requests\TurnoRequest;
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
            new OA\Response(response: 422, description: 'Datos inválidos: devuelve un mensaje por cada campo con error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'El usuario no es administrador.'),
        ]
    )]
    public function store(TurnoRequest $request)
    {
        $turno = Turno::create($request->validated());

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
            new OA\Response(response: 422, description: 'Datos inválidos: devuelve un mensaje por cada campo con error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'El usuario no es administrador.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
        ]
    )]
    public function update(TurnoRequest $request, string $id)
    {
        $turno = Turno::find($id);

        if (! $turno) {
            return response()->json([
                'message' => 'Turno no encontrado',
            ], 404);
        }

        $turno->update($request->validated());

        return response()->json([
            'message' => 'Turno actualizado correctamente',
            'turno' => $turno->fresh(),
        ], 200);
    }

    #[OA\Patch(
        path: '/api/turnos/{id}/bloqueo',
        summary: 'Bloquear o desbloquear un turno',
        description: 'Bloquea un turno libre (por ejemplo, por mantenimiento) para que no se pueda reservar. Al desbloquearlo vuelve a calcularse su estado.',
        tags: ['Turnos'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/id')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['bloqueado'],
            properties: [new OA\Property(property: 'bloqueado', type: 'boolean', example: true)]
        )),
        responses: [
            new OA\Response(response: 200, description: 'Estado del turno actualizado.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'El usuario no es administrador o la cancha no es de su club.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
            new OA\Response(response: 409, description: 'El turno tiene reservas activas.'),
        ]
    )]
    public function bloqueo(Request $request, string $id)
    {
        $request->validate(['bloqueado' => ['required', 'boolean']], [
            'bloqueado.required' => 'Indicá si el turno queda bloqueado (true) o no (false).',
        ]);

        $turno = Turno::with('cancha.club')->find($id);

        if (! $turno) {
            return response()->json([
                'message' => 'Turno no encontrado',
            ], 404);
        }

        if ($turno->cancha?->club?->administrador_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Solo podés bloquear turnos de las canchas de tus propios clubes.',
            ], 403);
        }

        if ($request->boolean('bloqueado')) {
            $tieneReservas = $turno->reservas()->where('estado', '!=', 'cancelada')->exists();
            if ($tieneReservas) {
                return response()->json([
                    'message' => 'El turno tiene reservas activas: cancelalas antes de bloquearlo.',
                ], 409);
            }

            $turno->update(['estado' => 'bloqueado']);
        } else {
            // Se saca el bloqueo y el estado vuelve a salir de sus reservas
            $turno->update(['estado' => 'disponible']);
            $turno->recalcularEstado();
        }

        return response()->json([
            'message' => $request->boolean('bloqueado') ? 'Turno bloqueado' : 'Turno desbloqueado',
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
            new OA\Response(response: 403, description: 'El usuario no es administrador.'),
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
