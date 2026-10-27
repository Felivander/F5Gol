<?php

namespace App\Http\Controllers;

use App\Models\Cancha;
use App\Models\Club;
use App\Http\Requests\CanchaRequest;
use App\Http\Requests\FotoCanchaRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            new OA\Response(response: 422, description: 'Datos inválidos: devuelve un mensaje por cada campo con error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'El usuario no es administrador o el club no es suyo.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
        ]
    )]
    public function update(CanchaRequest $request, string $id)
    {
        $cancha = Cancha::find($id);

        if (! $cancha) {
            return response()->json([
                'message' => 'Cancha no encontrada',
            ], 404);
        }

        // Tiene que ser dueño del club actual y, si la mueve, también del club nuevo
        if (! $this->esDuenoDelClub($request, $cancha->club_id)
            || ($request->has('club_id') && ! $this->esDuenoDelClub($request, $request->input('club_id')))) {
            return $this->respuestaNoEsDueno();
        }

        $cancha->update($request->validated());

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
            new OA\Response(response: 403, description: 'El usuario no es administrador o el club no es suyo.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
        ]
    )]
    public function destroy(Request $request, string $id)
    {
        $cancha = Cancha::find($id);

        if (! $cancha) {
            return response()->json([
                'message' => 'Cancha no encontrada',
            ], 404);
        }

        if (! $this->esDuenoDelClub($request, $cancha->club_id)) {
            return $this->respuestaNoEsDueno();
        }

        $cancha->delete();
        Storage::disk('public')->deleteDirectory("canchas/{$cancha->id}");

        return response()->json([
            'message' => 'Cancha eliminada correctamente',
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
    public function subirFoto(FotoCanchaRequest $request, string $id)
    {
        $cancha = Cancha::find($id);

        if (! $cancha) {
            return response()->json([
                'message' => 'Cancha no encontrada',
            ], 404);
        }

        if (! $this->esDuenoDelClub($request, $cancha->club_id)) {
            return $this->respuestaNoEsDueno();
        }

        $ruta = $request->file('foto')->store("canchas/{$cancha->id}", 'public');

        $fotos = $cancha->fotos ?? [];
        $fotos[] = Storage::disk('public')->url($ruta);
        $cancha->update(['fotos' => $fotos]);

        return response()->json([
            'message' => 'Foto subida correctamente',
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
                'message' => 'Cancha no encontrada',
            ], 404);
        }

        if (! $this->esDuenoDelClub($request, $cancha->club_id)) {
            return $this->respuestaNoEsDueno();
        }

        $fotos = $cancha->fotos ?? [];

        if (! array_key_exists($indice, $fotos)) {
            return response()->json([
                'message' => 'Foto no encontrada',
            ], 404);
        }

        $archivo = basename(parse_url($fotos[$indice], PHP_URL_PATH));
        Storage::disk('public')->delete("canchas/{$cancha->id}/{$archivo}");

        array_splice($fotos, $indice, 1);
        $cancha->update(['fotos' => $fotos]);

        return response()->json([
            'message' => 'Foto borrada correctamente',
            'cancha' => $cancha->fresh(),
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
