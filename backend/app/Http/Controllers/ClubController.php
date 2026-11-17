<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GestionaFotos;
use App\Http\Requests\ClubRequest;
use App\Http\Requests\FotoRequest;
use App\Models\Cancha;
use App\Models\Club;
use App\Models\Reserva;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class ClubController extends Controller
{
    use GestionaFotos;

    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
        path: '/api/clubs',
        summary: 'Listar clubs',
        description: 'Con buscar, devuelve solo los clubes cuyo nombre o dirección contienen ese texto. Con mios=1 y el token de un administrador, devuelve solo sus clubes. Cada club trae canchas_count: las canchas activas (en "mis clubes", todas las que no están eliminadas).',
        tags: ['Clubes'],
        parameters: [
            new OA\Parameter(name: 'buscar', description: 'Texto a buscar en el nombre o la dirección del club', in: 'query', required: false, schema: new OA\Schema(type: 'string', maxLength: 100), example: 'norte'),
            new OA\Parameter(name: 'mios', description: 'Solo los clubes del administrador logueado (requiere token)', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'), example: 1),
            new OA\Parameter(name: 'page', description: 'Número de página', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Listado paginado (10 por página).'),
            new OA\Response(response: 401, description: 'Se pidió mios=1 sin token.'),
        ]
    )]
    public function index(Request $request)
    {
        $buscar = mb_substr(trim((string) $request->query('buscar', '')), 0, 100);
        $mios = $request->boolean('mios');
        $usuario = auth('sanctum')->user();

        if ($mios && ! $usuario) {
            return response()->json(['message' => 'Tenés que iniciar sesión para ver tus clubes.'], 401);
        }

        $clubs = Club::with('administrador:id,name,email')
            // El público cuenta solo las canchas activas; el dueño, todas
            ->withCount(['canchas' => fn ($q) => $mios ? $q : $q->where('estado', 'activa')])
            ->when($mios, fn ($query) => $query->where('administrador_id', $usuario->id))
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
        description: 'El club queda a nombre del administrador logueado: el dueño no se manda en el pedido.',
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
        $club = Club::create([
            ...$request->validated(),
            'administrador_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Club creado correctamente',
            'club' => $club->fresh(),
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
            new OA\Response(response: 403, description: 'El usuario no es administrador o el club no es suyo.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
        ]
    )]
    public function update(ClubRequest $request, string $id)
    {
        $club = Club::find($id);

        if (! $club) {
            return $this->respuestaNoEncontrado();
        }

        if (! $this->esDueno($request, $club)) {
            return $this->respuestaNoEsDueno();
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
        description: 'Solo se puede eliminar un club sin canchas activas. Si tiene canchas eliminadas, se borran con él, salvo que tengan reservas registradas.',
        tags: ['Clubes'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/id')],
        responses: [
            new OA\Response(response: 200, description: 'Eliminado correctamente.', content: new OA\JsonContent(ref: '#/components/schemas/Mensaje')),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'El usuario no es administrador o el club no es suyo.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
            new OA\Response(response: 409, description: 'El club todavía tiene canchas activas o sus canchas eliminadas tienen reservas.'),
        ]
    )]
    public function destroy(Request $request, string $id)
    {
        $club = Club::find($id);

        if (! $club) {
            return $this->respuestaNoEncontrado();
        }

        if (! $this->esDueno($request, $club)) {
            return $this->respuestaNoEsDueno();
        }

        if (Cancha::where('club_id', $club->id)->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar un club que tiene canchas. Eliminá primero sus canchas.',
            ], 409);
        }

        // Borrar el club borra en cascada sus canchas eliminadas y sus turnos:
        // si alguna vez tuvieron reservas, se conservan para el historial
        $canchasEliminadas = Cancha::onlyTrashed()->where('club_id', $club->id)->pluck('id');
        if (Reserva::whereHas('turno', fn ($q) => $q->whereIn('cancha_id', $canchasEliminadas))->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el club porque sus canchas tienen reservas registradas.',
            ], 409);
        }

        Storage::disk('public')->deleteDirectory("clubs/{$club->id}");
        $club->delete();

        return response()->json([
            'message' => 'Club eliminado correctamente',
        ], 200);
    }

    #[OA\Post(
        path: '/api/clubs/{id}/fotos',
        summary: 'Subir una foto del club',
        description: 'Agrega la URL pública de la foto al final del array fotos del club.',
        tags: ['Clubes'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/id')],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['foto'],
                    properties: [new OA\Property(property: 'foto', description: 'Imagen jpg, png o webp de hasta 4 MB', type: 'string', format: 'binary')]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Foto subida: devuelve el club con el array fotos actualizado.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'El usuario no es administrador o el club no es suyo.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
            new OA\Response(response: 422, description: 'No es una imagen válida o pesa más de 4 MB.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ]
    )]
    public function subirFoto(FotoRequest $request, string $id)
    {
        $club = Club::find($id);

        if (! $club) {
            return $this->respuestaNoEncontrado();
        }

        if (! $this->esDueno($request, $club)) {
            return $this->respuestaNoEsDueno();
        }

        $this->agregarFoto($club, $request->file('foto'), 'clubs');

        return response()->json([
            'message' => 'Foto subida correctamente',
            'club' => $club->fresh(),
        ], 201);
    }

    #[OA\Delete(
        path: '/api/clubs/{id}/fotos/{indice}',
        summary: 'Borrar una foto del club',
        description: 'Borra la foto que está en esa posición del array fotos (empieza en 0). Las siguientes se corren un lugar.',
        tags: ['Clubes'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/id'),
            new OA\Parameter(name: 'indice', description: 'Posición de la foto en el array fotos', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 0), example: 0),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Foto borrada: devuelve el club con el array fotos actualizado.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'El usuario no es administrador o el club no es suyo.'),
            new OA\Response(response: 404, description: 'No existe el club o la foto.'),
        ]
    )]
    public function borrarFoto(Request $request, string $id, int $indice)
    {
        $club = Club::find($id);

        if (! $club) {
            return $this->respuestaNoEncontrado();
        }

        if (! $this->esDueno($request, $club)) {
            return $this->respuestaNoEsDueno();
        }

        if (! $this->quitarFoto($club, $indice, 'clubs')) {
            return response()->json([
                'message' => 'Foto no encontrada',
            ], 404);
        }

        return response()->json([
            'message' => 'Foto borrada correctamente',
            'club' => $club->fresh(),
        ], 200);
    }

    // Cada administrador solo gestiona sus propios clubes
    private function esDueno(Request $request, Club $club): bool
    {
        return $club->administrador_id === $request->user()->id;
    }

    private function respuestaNoEsDueno()
    {
        return response()->json([
            'message' => 'Solo podés gestionar tus propios clubes.',
        ], 403);
    }

    private function respuestaNoEncontrado()
    {
        return response()->json([
            'message' => 'Club no encontrado',
        ], 404);
    }
}
