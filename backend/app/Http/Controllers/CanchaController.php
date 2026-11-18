<?php

namespace App\Http\Controllers;

use App\Models\Cancha;
use App\Models\Club;
use App\Http\Requests\CanchaRequest;
use App\Http\Controllers\Concerns\GestionaFotos;
use App\Http\Requests\FotoRequest;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CanchaController extends Controller
{
    use GestionaFotos;

    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
        path: '/api/canchas',
        summary: 'Listar canchas',
        tags: ['Canchas'],
        parameters: [
            new OA\Parameter(name: 'club_id', description: 'Filtrar por club', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'incluir_inactivas', description: 'Solo administradores: incluir las canchas inactivas', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
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

        // Las canchas dadas de baja no se muestran al público;
        // un administrador puede pedirlas con ?incluir_inactivas=1 para gestionarlas
        $esAdministrador = auth('sanctum')->user()?->rol === 'administrador';
        if (! ($esAdministrador && $request->boolean('incluir_inactivas'))) {
            $query->where('estado', 'activa');
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
            new OA\Response(response: 422, description: 'Datos inválidos: devuelve un mensaje por cada campo con error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'El usuario no es administrador o el club no es suyo.'),
        ]
    )]
    public function store(CanchaRequest $request)
    {
        if (! $this->esDuenoDelClub($request, $request->input('club_id'))) {
            return $this->respuestaNoEsDueno();
        }

        $cancha = Cancha::create($request->validated());

        return response()->json([
            'message' => "La cancha {$cancha->nombre} se creó correctamente.",
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
            new OA\Response(response: 422, description: 'Datos inválidos: devuelve un mensaje por cada campo con error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'El usuario no es administrador o el club no es suyo.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
            new OA\Response(response: 409, description: 'Se intentó desactivar una cancha con reservas futuras.'),
        ]
    )]
    public function update(CanchaRequest $request, string $id)
    {
        $cancha = Cancha::find($id);

        if (! $cancha) {
            return response()->json([
                'message' => "No existe una cancha con el id {$id}.",
            ], 404);
        }

        // Tiene que ser dueño del club actual y, si la mueve, también del club nuevo
        if (! $this->esDuenoDelClub($request, $cancha->club_id)
            || ($request->has('club_id') && ! $this->esDuenoDelClub($request, $request->input('club_id')))) {
            return $this->respuestaNoEsDueno();
        }

        if ($request->input('estado') === 'inactiva' && $cancha->estado !== 'inactiva' && $cancha->tieneReservasFuturas()) {
            return response()->json([
                'message' => 'La cancha tiene reservas futuras: cancelalas antes de desactivarla.',
            ], 409);
        }

        $cancha->update($request->validated());

        return response()->json([
            'message' => "Los cambios de la cancha {$cancha->nombre} se guardaron.",
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
            new OA\Response(response: 403, description: 'El usuario no es administrador o el club no es suyo.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
        ]
    )]
    public function destroy(Request $request, string $id)
    {
        $cancha = Cancha::find($id);

        if (! $cancha) {
            return response()->json([
                'message' => "No existe una cancha con el id {$id}.",
            ], 404);
        }

        if (! $this->esDuenoDelClub($request, $cancha->club_id)) {
            return $this->respuestaNoEsDueno();
        }

        if ($cancha->tieneReservasFuturas()) {
            return response()->json([
                'message' => 'La cancha tiene reservas futuras: cancelalas antes de borrarla.',
            ], 409);
        }

        // Al usar SoftDeletes, delete() no borra la fila: completa deleted_at
        $cancha->delete();

        return response()->json([
            'message' => "La cancha {$cancha->nombre} se eliminó. Podés restaurarla desde Canchas eliminadas.",
        ], 200);
    }

    #[OA\Post(
        path: '/api/canchas/{id}/fotos',
        summary: 'Subir una foto de la cancha',
        description: 'Agrega la URL pública de la foto al final del array fotos de la cancha.',
        tags: ['Canchas'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/id')],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['foto'],
                    properties: [
                        new OA\Property(property: 'foto', type: 'string', format: 'binary', description: 'Imagen jpg, png o webp de hasta 4 MB.'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Foto subida: devuelve la cancha con el array fotos actualizado.'),
            new OA\Response(response: 422, description: 'El archivo no es una imagen válida o pesa más de 4 MB.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'El usuario no es administrador o el club no es suyo.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
        ]
    )]
    public function subirFoto(FotoRequest $request, string $id)
    {
        $cancha = Cancha::find($id);

        if (! $cancha) {
            return response()->json([
                'message' => "No existe una cancha con el id {$id}.",
            ], 404);
        }

        if (! $this->esDuenoDelClub($request, $cancha->club_id)) {
            return $this->respuestaNoEsDueno();
        }

        $this->agregarFoto($cancha, $request->file('foto'), 'canchas');

        return response()->json([
            'message' => 'La foto se subió correctamente.',
            'cancha' => $cancha->fresh(),
        ], 201);
    }

    #[OA\Delete(
        path: '/api/canchas/{id}/fotos/{indice}',
        summary: 'Borrar una foto de la cancha',
        description: 'Borra la foto que está en esa posición del array fotos (empieza en 0). Las siguientes se corren un lugar.',
        tags: ['Canchas'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/id'),
            new OA\Parameter(name: 'indice', description: 'Posición de la foto en el array fotos', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 0), example: 0),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Foto borrada: devuelve la cancha con el array fotos actualizado.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'El usuario no es administrador o el club no es suyo.'),
            new OA\Response(response: 404, description: 'No existe la cancha o la foto.'),
        ]
    )]
    public function borrarFoto(Request $request, string $id, int $indice)
    {
        $cancha = Cancha::find($id);

        if (! $cancha) {
            return response()->json([
                'message' => "No existe una cancha con el id {$id}.",
            ], 404);
        }

        if (! $this->esDuenoDelClub($request, $cancha->club_id)) {
            return $this->respuestaNoEsDueno();
        }

        if (! $this->quitarFoto($cancha, $indice, 'canchas')) {
            return response()->json([
                'message' => "La cancha no tiene una foto en la posición {$indice} (las fotos se cuentan desde 0).",
            ], 404);
        }

        return response()->json([
            'message' => 'La foto se borró.',
            'cancha' => $cancha->fresh(),
        ], 200);
    }

    /**
     * Display a listing of the soft-deleted resources.
     */
    #[OA\Get(
        path: '/api/canchas/eliminadas',
        summary: 'Listar canchas eliminadas',
        tags: ['Canchas'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'club_id', description: 'Filtrar por club', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', description: 'Número de página', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Listado paginado (10 por página) de canchas eliminadas.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
        ]
    )]
    public function eliminadas(Request $request)
    {
        $query = Cancha::onlyTrashed()
            ->with('club:id,nombre')
            ->orderByDesc('deleted_at');

        if ($request->has('club_id')) {
            $query->where('club_id', $request->query('club_id'));
        }

        return response()->json($query->paginate(10), 200);
    }

    /**
     * Restore the specified soft-deleted resource.
     */
    #[OA\Put(
        path: '/api/canchas/{id}/restore',
        summary: 'Restaurar cancha eliminada',
        tags: ['Canchas'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/id')],
        responses: [
            new OA\Response(response: 200, description: 'Restaurada correctamente.', content: new OA\JsonContent(ref: '#/components/schemas/Mensaje')),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 404, description: 'No encontrado entre las canchas eliminadas.'),
        ]
    )]
    public function restore(Request $request, string $id)
    {
        $cancha = Cancha::onlyTrashed()->find($id);

        if (! $cancha) {
            return response()->json([
                'message' => "No hay una cancha eliminada con el id {$id}.",
            ], 404);
        }

        if (! $this->esDuenoDelClub($request, $cancha->club_id)) {
            return $this->respuestaNoEsDueno();
        }

        $cancha->restore();

        return response()->json([
            'message' => "La cancha {$cancha->nombre} se restauró y vuelve a estar en el club.",
        ], 200);
    }

    // Un administrador solo gestiona las canchas de los clubes que administra
    private function esDuenoDelClub(Request $request, mixed $clubId): bool
    {
        return Club::where('id', $clubId)
            ->where('administrador_id', $request->user()->id)
            ->exists();
    }

    private function respuestaNoEsDueno()
    {
        return response()->json([
            'message' => 'Solo podés gestionar las canchas de tus propios clubes.',
        ], 403);
    }
}
