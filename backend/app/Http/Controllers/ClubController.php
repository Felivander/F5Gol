<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Http\Requests\ClubRequest;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ClubController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
        path: '/api/clubs',
        summary: 'Listar clubs',
        description: 'Con buscar, devuelve solo los clubes cuyo nombre o dirección contienen ese texto.',
        tags: ['Clubes'],
        parameters: [
            new OA\Parameter(name: 'buscar', description: 'Texto a buscar en el nombre o la dirección del club', in: 'query', required: false, schema: new OA\Schema(type: 'string', maxLength: 100), example: 'norte'),
            new OA\Parameter(name: 'page', description: 'Número de página', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Listado paginado (10 por página).'),
        ]
    )]
    public function index(Request $request)
    {
        $buscar = mb_substr(trim((string) $request->query('buscar', '')), 0, 100);

        $clubs = Club::with('administrador:id,name,email')
            ->when($buscar !== '', function ($query) use ($buscar) {
                $query->where(function ($q) use ($buscar) {
                    $q->where('nombre', 'like', "%{$buscar}%")
                        ->orWhere('direccion', 'like', "%{$buscar}%");
                });
            })
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        return response()->json($clubs, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    #[OA\Post(
        path: '/api/clubs',
        summary: 'Crear club',
        tags: ['Clubes'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ClubInput')),
        responses: [
            new OA\Response(response: 201, description: 'Creado correctamente.'),
            new OA\Response(response: 422, description: 'Datos inválidos: devuelve un mensaje por cada campo con error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'El usuario no es administrador.'),
        ]
    )]
    public function store(ClubRequest $request)
    {
        $club = Club::create($request->validated());

        return response()->json([
            'message' => 'Club creado correctamente',
            'club' => $club,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    #[OA\Get(
        path: '/api/clubs/{id}',
        summary: 'Obtener club',
        tags: ['Clubes'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/id')],
        responses: [
            new OA\Response(response: 200, description: 'Operación exitosa.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
        ]
    )]
    public function show(string $id)
    {
        $club = Club::findOrFail($id);

        // El dueño del club ve también sus canchas inactivas; el público, solo las activas
        $esDueno = auth('sanctum')->id() === $club->administrador_id;
        $club->load(['canchas' => fn ($q) => $esDueno ? $q : $q->where('estado', 'activa')]);

        return response()->json($club, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    #[OA\Put(
        path: '/api/clubs/{id}',
        summary: 'Actualizar club',
        tags: ['Clubes'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/id')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ClubInput')),
        responses: [
            new OA\Response(response: 200, description: 'Actualizado correctamente.'),
            new OA\Response(response: 422, description: 'Datos inválidos: devuelve un mensaje por cada campo con error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'El usuario no es administrador.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
        ]
    )]
    public function update(ClubRequest $request, string $id)
    {
        $club = Club::find($id);

        if (! $club) {
            return response()->json([
                'message' => 'Club no encontrado',
            ], 404);
        }

        $club->update($request->validated());

        return response()->json([
            'message' => 'Club actualizado correctamente',
            'club' => $club->fresh(),
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    #[OA\Delete(
        path: '/api/clubs/{id}',
        summary: 'Eliminar club',
        tags: ['Clubes'],
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
        $club = Club::find($id);

        if (! $club) {
            return response()->json([
                'message' => 'Club no encontrado',
            ], 404);
        }

        $club->delete();

        return response()->json([
            'message' => 'Club eliminado correctamente',
        ], 200);
    }
}
