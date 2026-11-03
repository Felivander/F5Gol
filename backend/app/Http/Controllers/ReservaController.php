<?php

namespace App\Http\Controllers;

use App\Models\Reserva;
use App\Models\Turno;
use App\Models\User;
use App\Http\Requests\ReservaRequest;
use App\Notifications\PartidoCompleto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use OpenApi\Attributes as OA;

class ReservaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * "Mis reservas": /api/reservas?usuario_id=1
     */
    #[OA\Get(
        path: '/api/reservas',
        summary: 'Listar reservas',
        tags: ['Reservas'],
        parameters: [
            new OA\Parameter(name: 'usuario_id', description: 'Filtrar por usuario ("mis reservas")', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'turno_id', description: 'Filtrar por turno', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'cancha_id', description: 'Filtrar por cancha del turno', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'fecha', description: 'Filtrar por fecha del turno (AAAA-MM-DD)', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'desde', description: 'Turnos desde esta fecha inclusive (AAAA-MM-DD), por ejemplo el lunes de la semana', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'hasta', description: 'Turnos hasta esta fecha inclusive (AAAA-MM-DD), por ejemplo el domingo de la semana', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'mis_clubes', description: 'Con token de administrador: solo las reservas de las canchas de sus clubes', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'por_pagina', description: 'Reservas por página (de 1 a 100, por defecto 10)', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', description: 'Número de página', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Listado paginado (10 por página salvo que se pida otra cantidad).'),
        ]
    )]
    public function index(Request $request)
    {
        $query = Reserva::with(['turno.cancha', 'usuario:id,name,email'])
            ->orderBy('created_at', 'desc');

        if ($request->has('usuario_id')) {
            $query->where('usuario_id', $request->query('usuario_id'));
        }

        if ($request->has('turno_id')) {
            $query->where('turno_id', $request->query('turno_id'));
        }

        // Para el panel del administrador: reservas de una cancha en un día
        if ($request->has('cancha_id')) {
            $query->whereHas('turno', fn ($q) => $q->where('cancha_id', $request->query('cancha_id')));
        }

        if ($request->has('fecha')) {
            $query->whereHas('turno', fn ($q) => $q->whereDate('fecha', $request->query('fecha')));
        }

        // Rango de fechas, para ver la semana completa de una cancha
        if ($request->has('desde')) {
            $query->whereHas('turno', fn ($q) => $q->whereDate('fecha', '>=', $request->query('desde')));
        }

        if ($request->has('hasta')) {
            $query->whereHas('turno', fn ($q) => $q->whereDate('fecha', '<=', $request->query('hasta')));
        }

        // Panel del administrador: solo las reservas de las canchas de sus clubes
        if ($request->boolean('mis_clubes')) {
            $administradorId = auth('sanctum')->id();
            $query->whereHas('turno.cancha.club', fn ($q) => $q->where('administrador_id', $administradorId));
        }

        // El panel pide todas las del día de una vez; se limita a 100 por página
        $porPagina = min(max((int) $request->query('por_pagina', 10), 1), 100);

        return response()->json($query->paginate($porPagina), 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * Reserva completa: ocupa el turno entero.
     * Reserva individual (matchmaking): suma jugadores al cupo del turno
     * y solo lo marca "ocupado" cuando se completa el cupo_maximo.
     */
    #[OA\Post(
        path: '/api/reservas',
        summary: 'Crear reserva',
        tags: ['Reservas'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ReservaInput')),
        responses: [
            new OA\Response(response: 201, description: 'Creado correctamente.'),
            new OA\Response(response: 422, description: 'Datos inválidos (incluye un turno_id que no existe): devuelve un mensaje por cada campo con error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 409, description: 'Turno no disponible, ya pasado, con jugadores anotados o sin cupo suficiente.'),
        ]
    )]
    public function store(ReservaRequest $request)
    {
        // Todo pasa dentro de una transacción con el turno bloqueado (lockForUpdate):
        // si dos jugadores reservan el mismo turno a la vez, el segundo espera
        // a que termine el primero y ve el estado ya actualizado.
        return DB::transaction(function () use ($request) {
            $turno = Turno::lockForUpdate()->find($request->input('turno_id'));

            // La regla exists del ReservaRequest ya responde 422 si el turno no existe;
            // esto cubre el caso de que lo borren entre la validación y el bloqueo,
            // con la misma respuesta para que el front maneje un solo caso.
            if (! $turno) {
                return response()->json([
                    'message' => 'El turno indicado no existe.',
                    'errors' => ['turno_id' => ['El turno indicado no existe.']],
                ], 422);
            }

            if (in_array($turno->estado, ['ocupado', 'bloqueado'])) {
                return response()->json([
                    'message' => 'El turno ya no está disponible',
                ], 409);
            }

            if ($turno->yaPaso()) {
                return response()->json([
                    'message' => 'No se puede reservar un turno que ya pasó',
                ], 409);
            }

            $tipo = $request->input('tipo', 'completa');
            $cantidad = (int) $request->input('cantidad_jugadores', 1);

            // Si ya hay jugadores anotados por matchmaking, el turno no se puede reservar entero
            if ($tipo === 'completa' && $turno->cupoActual() > 0) {
                return response()->json([
                    'message' => 'El turno ya tiene jugadores anotados: solo se puede sumar al partido',
                ], 409);
            }

            if ($tipo === 'individual') {
                $cupoDisponible = $turno->cupo_maximo - $turno->cupoActual();

                if ($cantidad > $cupoDisponible) {
                    return response()->json([
                        'message' => 'No hay cupo suficiente en este turno',
                    ], 409);
                }
            }

            $datos = $request->validated();
            // Un jugador no elige el estado de pago: arranca pendiente hasta que el club lo marque
            if ($request->user()->rol !== 'administrador') {
                unset($datos['estado_pago']);
            }

            $reserva = Reserva::create(array_merge($datos, [
                'estado' => 'confirmada',
            ]));

            if ($tipo === 'completa') {
                $turno->update(['estado' => 'ocupado']);
            } elseif ($turno->cupoActual() >= $turno->cupo_maximo) {
                // Se completó el cupo de matchmaking: el turno queda confirmado y se avisa a todos los anotados
                $turno->update(['estado' => 'ocupado']);
                $anotados = User::whereIn('id', $turno->reservas()
                    ->where('tipo', 'individual')
                    ->where('estado', '!=', 'cancelada')
                    ->pluck('usuario_id'))->get();
                Notification::send($anotados, new PartidoCompleto($turno->load('cancha')));
            }

            return response()->json([
                'message' => 'Reserva creada correctamente',
                'reserva' => $reserva->fresh(),
            ], 201);
        });
    }

    /**
     * Display the specified resource.
     */
    #[OA\Get(
        path: '/api/reservas/{id}',
        summary: 'Obtener reserva',
        tags: ['Reservas'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/id')],
        responses: [
            new OA\Response(response: 200, description: 'Operación exitosa.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
        ]
    )]
    public function show(string $id)
    {
        $reserva = Reserva::with(['turno.cancha', 'usuario:id,name,email', 'jugadorAnotados.usuario:id,name'])
            ->findOrFail($id);

        return response()->json($reserva, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    #[OA\Put(
        path: '/api/reservas/{id}',
        summary: 'Actualizar reserva',
        tags: ['Reservas'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/id')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ReservaInput')),
        responses: [
            new OA\Response(response: 200, description: 'Actualizado correctamente.'),
            new OA\Response(response: 422, description: 'Datos inválidos: devuelve un mensaje por cada campo con error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'Un jugador intentó cambiar el estado de pago.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
            new OA\Response(response: 409, description: 'El turno nuevo no está disponible o no tiene cupo.'),
        ]
    )]
    public function update(ReservaRequest $request, string $id)
    {
        $reserva = Reserva::find($id);

        if (! $reserva) {
            return response()->json([
                'message' => 'Reserva no encontrada',
            ], 404);
        }

        // El jugador solo edita sus reservas; el administrador, las de las canchas de sus clubes
        $usuario = $request->user();
        $esAdministradorDelClub = $usuario->rol === 'administrador'
            && $reserva->turno?->cancha?->club?->administrador_id === $usuario->id;
        if (! $esAdministradorDelClub && $reserva->usuario_id !== $usuario->id) {
            return response()->json([
                'message' => 'Solo podés editar tus propias reservas o las de tus clubes.',
            ], 403);
        }

        // El estado de pago lo maneja el club: un jugador no puede marcar su reserva como pagada
        if ($request->has('estado_pago') && ! $esAdministradorDelClub) {
            return response()->json([
                'message' => 'Solo el administrador del club puede cambiar el estado de pago',
            ], 403);
        }

        $turnoAnterior = $reserva->turno;
        $nuevoTurnoId = $request->input('turno_id');

        // Cambio de turno: el nuevo tiene que estar libre y no haber pasado
        if ($nuevoTurnoId && (int) $nuevoTurnoId !== $reserva->turno_id) {
            // Mismo plazo que para cancelar: el jugador no puede mover un turno que empieza en menos de 2 horas
            $limite = $turnoAnterior?->inicio()->subHours(Reserva::HORAS_MINIMAS_PARA_CANCELAR);
            if ($request->user()->rol !== 'administrador' && $limite && now()->greaterThan($limite)) {
                return response()->json([
                    'message' => 'Las reservas se pueden cambiar de turno hasta '.Reserva::HORAS_MINIMAS_PARA_CANCELAR.' horas antes del partido.',
                ], 409);
            }

            $nuevoTurno = Turno::find($nuevoTurnoId);

            if (in_array($nuevoTurno->estado, ['ocupado', 'bloqueado']) || $nuevoTurno->yaPaso()) {
                return response()->json([
                    'message' => 'El turno nuevo no está disponible',
                ], 409);
            }

            $cantidad = (int) $request->input('cantidad_jugadores', $reserva->cantidad_jugadores);
            if ($request->input('tipo', $reserva->tipo) === 'individual'
                && $cantidad > $nuevoTurno->cupo_maximo - $nuevoTurno->cupoActual()) {
                return response()->json([
                    'message' => 'No hay cupo suficiente en el turno nuevo',
                ], 409);
            }
        } elseif ($reserva->tipo === 'individual' && $reserva->estado !== 'cancelada' && $request->has('cantidad_jugadores')) {
            // Mismo turno: los jugadores extra tienen que entrar en el cupo que queda libre
            $extra = (int) $request->input('cantidad_jugadores') - $reserva->cantidad_jugadores;
            $libre = $turnoAnterior->cupo_maximo - $turnoAnterior->cupoActual();

            if ($extra > $libre) {
                return response()->json([
                    'message' => 'No hay cupo suficiente en este turno',
                ], 409);
            }
        }

        $reserva->update($request->validated());

        // Al cambiar la cantidad de jugadores, el estado o el turno,
        // el cupo puede llenarse o liberarse: se recalculan el turno anterior y el actual
        $turnoAnterior?->recalcularEstado();
        $reserva->fresh()->turno?->recalcularEstado();

        return response()->json([
            'message' => 'Reserva actualizada correctamente',
            'reserva' => $reserva->fresh(),
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * Cancelar una reserva libera el turno automáticamente.
     * La reserva no se borra: queda con estado "cancelada" en el historial.
     */
    #[OA\Delete(
        path: '/api/reservas/{id}',
        summary: 'Cancelar reserva',
        description: 'La reserva queda con estado "cancelada" (sigue apareciendo en el historial) y el turno se libera si corresponde.',
        tags: ['Reservas'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/id')],
        responses: [
            new OA\Response(response: 200, description: 'Cancelada correctamente.', content: new OA\JsonContent(ref: '#/components/schemas/Mensaje')),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
            new OA\Response(response: 409, description: 'La reserva ya estaba cancelada o está fuera del plazo de cancelación (hasta 2 horas antes del turno).'),
        ]
    )]
    public function destroy(Request $request, string $id)
    {
        $reserva = Reserva::find($id);

        if (! $reserva) {
            return response()->json([
                'message' => 'Reserva no encontrada',
            ], 404);
        }

        if ($reserva->estado === 'cancelada') {
            return response()->json([
                'message' => 'La reserva ya estaba cancelada',
            ], 409);
        }

        $turno = $reserva->turno;

        // El administrador del club puede cancelar siempre; el jugador, solo dentro del plazo
        $limite = $turno?->inicio()->subHours(Reserva::HORAS_MINIMAS_PARA_CANCELAR);
        if ($request->user()->rol !== 'administrador' && $limite && now()->greaterThan($limite)) {
            return response()->json([
                'message' => 'Las reservas se pueden cancelar hasta '.Reserva::HORAS_MINIMAS_PARA_CANCELAR.' horas antes del turno.',
            ], 409);
        }

        $reserva->update(['estado' => 'cancelada']);

        $turno?->recalcularEstado();

        return response()->json([
            'message' => 'Reserva cancelada correctamente',
        ], 200);
    }
}
