<?php

namespace App\Http\Controllers;

use App\Models\Cancha;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CanchaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
        path: '/api/canchas',
        summary: 'Listar canchas',
        tags: ['Canchas'],
        parameters: [
            new OA\Parameter(name: 'club_id', description: 'Filtrar por club', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', description: 'Número de página', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Listado paginado (10 por página).'),
        ]
    )]
    public function index(Request $request)
    {
        $query = Cancha::with('club:id,nombre')->orderBy('nombre');

        // Permite filtrar por club: /api/canchas?club_id=1
        if ($request->has('club_id')) {
            $query->where('club_id', $request->query('club_id'));
        }

        return response()->json($query->paginate(10), 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    #[OA\Post(
        path: '/api/canchas',
        summary: 'Crear cancha',
        tags: ['Canchas'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CanchaInput')),
        responses: [
            new OA\Response(response: 201, description: 'Creado correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
        ]
    )]
    public function store(Request $request)
    {
        $cancha = Cancha::create($request->all());

        return response()->json([
            'message' => 'Cancha creada correctamente',
            'cancha' => $cancha,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    #[OA\Get(
        path: '/api/canchas/{id}',
        summary: 'Obtener cancha',
        tags: ['Canchas'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/id')],
        responses: [
            new OA\Response(response: 200, description: 'Operación exitosa.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
        ]
    )]
    public function show(string $id)
    {
        $cancha = Cancha::with('club')->findOrFail($id);

        return response()->json($cancha, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    #[OA\Put(
        path: '/api/canchas/{id}',
        summary: 'Actualizar cancha',
        tags: ['Canchas'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/id')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CanchaInput')),
        responses: [
            new OA\Response(response: 200, description: 'Actualizado correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
        ]
    )]
    public function update(Request $request, string $id)
    {
        $cancha = Cancha::find($id);

        if (! $cancha) {
            return response()->json([
                'message' => 'Cancha no encontrada',
            ], 404);
        }

        $cancha->update($request->all());

        return response()->json([
            'message' => 'Cancha actualizada correctamente',
            'cancha' => $cancha->fresh(),
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    #[OA\Delete(
        path: '/api/canchas/{id}',
        summary: 'Eliminar cancha',
        tags: ['Canchas'],
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
        $cancha = Cancha::find($id);

        if (! $cancha) {
            return response()->json([
                'message' => 'Cancha no encontrada',
            ], 404);
        }

        $cancha->delete();

        return response()->json([
            'message' => 'Cancha eliminada correctamente',
        ], 200);
    }
}
