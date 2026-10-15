<?php

namespace App\Http\Controllers;

use App\Models\JugadorAnotado;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class JugadorAnotadoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
        path: '/api/jugador-anotados',
        summary: 'Listar jugador-anotados',
        tags: ['Jugadores anotados'],
        parameters: [
            new OA\Parameter(name: 'reserva_id', description: 'Filtrar por reserva', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Listado obtenido correctamente.'),
        ]
    )]
    public function index(Request $request)
    {
        $query = JugadorAnotado::with(['usuario:id,name,email', 'reserva.turno'])
            ->orderBy('fecha_inscripcion');

        if ($request->has('reserva_id')) {
            $query->where('reserva_id', $request->query('reserva_id'));
        }

        return response()->json($query->get(), 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    #[OA\Post(
        path: '/api/jugador-anotados',
        summary: 'Crear jugador anotado',
        tags: ['Jugadores anotados'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/JugadorAnotadoInput')),
        responses: [
            new OA\Response(response: 201, description: 'Creado correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
        ]
    )]
    public function store(Request $request)
    {
        $jugadorAnotado = JugadorAnotado::create($request->all());

        return response()->json([
            'message' => 'Jugador anotado correctamente',
            'jugador_anotado' => $jugadorAnotado,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    #[OA\Get(
        path: '/api/jugador-anotados/{id}',
        summary: 'Obtener jugador anotado',
        tags: ['Jugadores anotados'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/id')],
        responses: [
            new OA\Response(response: 200, description: 'Operación exitosa.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
        ]
    )]
    public function show(string $id)
    {
        $jugadorAnotado = JugadorAnotado::with(['usuario', 'reserva'])->findOrFail($id);

        return response()->json($jugadorAnotado, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    #[OA\Put(
        path: '/api/jugador-anotados/{id}',
        summary: 'Actualizar jugador anotado',
        tags: ['Jugadores anotados'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/id')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/JugadorAnotadoInput')),
        responses: [
            new OA\Response(response: 200, description: 'Actualizado correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
        ]
    )]
    public function update(Request $request, string $id)
    {
        $jugadorAnotado = JugadorAnotado::find($id);

        if (! $jugadorAnotado) {
            return response()->json([
                'message' => 'Jugador anotado no encontrado',
            ], 404);
        }

        $jugadorAnotado->update($request->all());

        return response()->json([
            'message' => 'Jugador anotado actualizado correctamente',
            'jugador_anotado' => $jugadorAnotado->fresh(),
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    #[OA\Delete(
        path: '/api/jugador-anotados/{id}',
        summary: 'Eliminar jugador anotado',
        tags: ['Jugadores anotados'],
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
        $jugadorAnotado = JugadorAnotado::find($id);

        if (! $jugadorAnotado) {
            return response()->json([
                'message' => 'Jugador anotado no encontrado',
            ], 404);
        }

        $jugadorAnotado->delete();

        return response()->json([
            'message' => 'Jugador anotado eliminado correctamente',
        ], 200);
    }
}
