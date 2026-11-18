<?php

namespace App\Http\Controllers;

use App\Models\JugadorAnotado;
use App\Models\Reserva;
use App\Http\Requests\JugadorAnotadoRequest;
use App\Models\User;
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
        description: 'Requiere sesión. Cada uno ve sus inscripciones y las de sus reservas; el administrador, también las de sus clubes.',
        tags: ['Jugadores anotados'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'reserva_id', description: 'Filtrar por reserva', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Listado obtenido correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
        ]
    )]
    public function index(Request $request)
    {
        $query = JugadorAnotado::with(['usuario:id,name,email', 'reserva.turno'])
            ->orderBy('fecha_inscripcion');

        $this->soloVisiblesPara($query, $request->user());

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
            new OA\Response(response: 422, description: 'Datos inválidos: devuelve un mensaje por cada campo con error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'La reserva no es suya ni de un club suyo.'),
        ]
    )]
    public function store(JugadorAnotadoRequest $request)
    {
        $reserva = Reserva::with('turno.cancha.club')->find($request->input('reserva_id'));

        if (! $this->puedeGestionarReserva($request->user(), $reserva)) {
            return response()->json([
                'message' => 'Solo quien hizo la reserva o el administrador del club pueden anotar jugadores en ella.',
            ], 403);
        }

        $jugadorAnotado = JugadorAnotado::create($request->validated());

        return response()->json([
            'message' => "El jugador quedó anotado en la reserva {$reserva->id}.",
            'jugador_anotado' => $jugadorAnotado,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    #[OA\Get(
        path: '/api/jugador-anotados/{id}',
        summary: 'Obtener jugador anotado',
        description: 'Requiere sesión. Solo lo ve el jugador, el dueño de la reserva o el administrador del club.',
        tags: ['Jugadores anotados'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/id')],
        responses: [
            new OA\Response(response: 200, description: 'Operación exitosa.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'No tiene permiso para verlo.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
        ]
    )]
    public function show(Request $request, string $id)
    {
        $jugadorAnotado = JugadorAnotado::with(['usuario:id,name,email', 'reserva'])->findOrFail($id);

        $visible = $this->soloVisiblesPara(JugadorAnotado::whereKey($jugadorAnotado->id), $request->user())->exists();
        if (! $visible) {
            return response()->json(['message' => 'No tenés permiso para ver esta inscripción.'], 403);
        }

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
            new OA\Response(response: 422, description: 'Datos inválidos: devuelve un mensaje por cada campo con error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'No es el jugador anotado, ni quien hizo la reserva, ni el administrador del club.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
        ]
    )]
    public function update(JugadorAnotadoRequest $request, string $id)
    {
        $jugadorAnotado = JugadorAnotado::find($id);

        if (! $jugadorAnotado) {
            return response()->json([
                'message' => "No existe una inscripción con el id {$id}.",
            ], 404);
        }

        if (! $this->puedeModificar($request->user(), $jugadorAnotado)) {
            return response()->json([
                'message' => 'Solo el jugador anotado, quien hizo la reserva o el administrador del club pueden cambiar esta inscripción.',
            ], 403);
        }

        $jugadorAnotado->update($request->validated());

        return response()->json([
            'message' => 'Los cambios de la inscripción se guardaron.',
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
            new OA\Response(response: 403, description: 'No es el jugador anotado, ni quien hizo la reserva, ni el administrador del club.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
        ]
    )]
    public function destroy(Request $request, string $id)
    {
        $jugadorAnotado = JugadorAnotado::find($id);

        if (! $jugadorAnotado) {
            return response()->json([
                'message' => "No existe una inscripción con el id {$id}.",
            ], 404);
        }

        if (! $this->puedeModificar($request->user(), $jugadorAnotado)) {
            return response()->json([
                'message' => 'Solo el jugador anotado, quien hizo la reserva o el administrador del club pueden cambiar esta inscripción.',
            ], 403);
        }

        $jugadorAnotado->delete();

        return response()->json([
            'message' => 'La inscripción se eliminó.',
        ], 200);
    }

    // Dueño de la reserva o administrador del club de esa cancha
    private function puedeGestionarReserva(User $usuario, ?Reserva $reserva): bool
    {
        if (! $reserva) {
            return false;
        }

        return $reserva->usuario_id === $usuario->id
            || ($usuario->rol === 'administrador' && $reserva->turno?->cancha?->club?->administrador_id === $usuario->id);
    }

    // Además de los anteriores, el propio jugador puede modificar o borrar su inscripción
    private function puedeModificar(User $usuario, JugadorAnotado $jugadorAnotado): bool
    {
        return $jugadorAnotado->usuario_id === $usuario->id
            || $this->puedeGestionarReserva($usuario, $jugadorAnotado->reserva()->with('turno.cancha.club')->first());
    }

    /**
     * Inscripciones que puede ver el usuario: las suyas, las de reservas que hizo él
     * y, si es administrador, las de las canchas de sus clubes.
     */
    private function soloVisiblesPara($query, User $usuario)
    {
        return $query->where(function ($q) use ($usuario) {
            $q->where('usuario_id', $usuario->id)
                ->orWhereHas('reserva', fn ($r) => $r->where('usuario_id', $usuario->id));

            if ($usuario->rol === 'administrador') {
                $q->orWhereHas('reserva.turno.cancha.club', fn ($c) => $c->where('administrador_id', $usuario->id));
            }
        });
    }
}
