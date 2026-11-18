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
        description: 'Requiere sesión. El jugador ve solo sus reservas; el administrador ve las suyas y las de las canchas de sus clubes.',
        tags: ['Reservas'],
        security: [['sanctum' => []]],
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
            new OA\Response(response: 401, description: 'No autenticado.'),
        ]
    )]
    public function index(Request $request)
    {
        $query = Reserva::with(['turno.cancha', 'usuario:id,name,email'])
            ->orderBy('created_at', 'desc');

        $this->soloVisiblesPara($query, $request->user());

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
            new OA\Response(response: 403, description: 'Se intentó reservar a nombre de otro usuario sin ser el administrador del club.'),
            new OA\Response(response: 409, description: 'El mensaje dice el motivo: turno bloqueado, ya reservado, ya empezado, con jugadores anotados (para reserva completa) o sin cupo suficiente.'),
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

            $usuario = $request->user();
            $esAdministradorDelClub = $this->esAdministradorDelClub($usuario, $turno);

            // Cada uno reserva a su nombre; solo el administrador del club puede cargar
            // una reserva para otra persona (por ejemplo, alguien que llamó por teléfono)
            if ((int) $request->input('usuario_id') !== $usuario->id && ! $esAdministradorDelClub) {
                return response()->json([
                    'message' => 'Solo podés reservar a tu nombre: usuario_id tiene que ser tu propio id.',
                ], 403);
            }

            if ($turno->estado === 'bloqueado') {
                return response()->json([
                    'message' => 'El turno '.$turno->textoHorario().' está bloqueado por mantenimiento. Elegí otro horario.',
                ], 409);
            }

            if ($turno->estado === 'ocupado') {
                return response()->json([
                    'message' => 'El turno '.$turno->textoHorario().' ya está reservado. Elegí otro horario.',
                ], 409);
            }

            if ($turno->yaPaso()) {
                return response()->json([
                    'message' => 'El turno '.$turno->textoHorario().' ya empezó o terminó: no se puede reservar.',
                ], 409);
            }

            $tipo = $request->input('tipo', 'completa');
            $cantidad = (int) $request->input('cantidad_jugadores', 1);
            $anotados = $turno->cupoActual();

            // Si ya hay jugadores anotados por matchmaking, el turno no se puede reservar entero
            if ($tipo === 'completa' && $anotados > 0) {
                return response()->json([
                    'message' => 'El turno '.$turno->textoHorario()." ya tiene {$anotados} de {$turno->cupo_maximo} jugadores anotados: "
                        .'no se puede reservar la cancha entera, pero podés sumarte al partido.',
                ], 409);
            }

            if ($tipo === 'individual') {
                $cupoDisponible = $turno->cupo_maximo - $anotados;

                if ($cantidad > $cupoDisponible) {
                    return response()->json([
                        'message' => $this->textoSinCupo($cupoDisponible, $cantidad, 'en el turno '.$turno->textoHorario()),
                    ], 409);
                }
            }

            $datos = $request->validated();
            // Un jugador no elige el estado de pago: arranca pendiente hasta que el club lo marque
            if (! $esAdministradorDelClub) {
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

            $horario = $turno->textoHorario();
            $completo = $turno->fresh()->estado === 'ocupado';

            return response()->json([
                'message' => match (true) {
                    $tipo === 'completa' => "Listo: reservaste la cancha entera para el turno {$horario}.",
                    $completo => "Te anotaste y se completó el partido del turno {$horario}. Les mandamos un mail a todos los jugadores.",
                    default => "Te anotaste al partido del turno {$horario}. Van {$turno->cupoActual()} de {$turno->cupo_maximo} jugadores.",
                },
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
        description: 'Requiere sesión. Solo la ve quien la hizo o el administrador del club de la cancha.',
        tags: ['Reservas'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/id')],
        responses: [
            new OA\Response(response: 200, description: 'Operación exitosa.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'La reserva no es suya ni de un club suyo.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
        ]
    )]
    public function show(Request $request, string $id)
    {
        $reserva = Reserva::with(['turno.cancha', 'usuario:id,name,email', 'jugadorAnotados.usuario:id,name'])
            ->findOrFail($id);

        $visible = $this->soloVisiblesPara(Reserva::whereKey($reserva->id), $request->user())->exists();
        if (! $visible) {
            return response()->json(['message' => 'No tenés permiso para ver esta reserva.'], 403);
        }

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
                'message' => "No existe una reserva con el id {$id}.",
            ], 404);
        }

        // El jugador solo edita sus reservas; el administrador, las de las canchas de sus clubes
        $usuario = $request->user();
        $esAdministradorDelClub = $this->esAdministradorDelClub($usuario, $reserva->turno);
        if (! $esAdministradorDelClub && $reserva->usuario_id !== $usuario->id) {
            return response()->json([
                'message' => 'Solo podés editar tus propias reservas o las de tus clubes.',
            ], 403);
        }

        // El estado de pago lo maneja el club: un jugador no puede marcar su reserva como pagada
        if ($request->has('estado_pago') && ! $esAdministradorDelClub) {
            return response()->json([
                'message' => 'Solo el administrador del club puede cambiar el estado de pago.',
            ], 403);
        }

        // La reserva siempre queda a nombre de quien la hizo
        if ($request->has('usuario_id') && (int) $request->input('usuario_id') !== $reserva->usuario_id) {
            return response()->json([
                'message' => 'No se puede cambiar el titular de una reserva: cancelala y que la otra persona reserve a su nombre.',
                'errors' => ['usuario_id' => ['No se puede cambiar el titular de una reserva.']],
            ], 422);
        }

        $turnoAnterior = $reserva->turno;
        $nuevoTurnoId = $request->input('turno_id');

        // Cambio de turno: el nuevo tiene que estar libre y no haber pasado
        if ($nuevoTurnoId && (int) $nuevoTurnoId !== $reserva->turno_id) {
            // Mismo plazo que para cancelar: el jugador no puede mover un turno que empieza en menos de 2 horas
            $limite = $turnoAnterior?->inicio()->subHours(Reserva::HORAS_MINIMAS_PARA_CANCELAR);
            if (! $esAdministradorDelClub && $limite && now()->greaterThan($limite)) {
                return response()->json([
                    'message' => 'Las reservas se pueden cambiar de turno hasta '.Reserva::HORAS_MINIMAS_PARA_CANCELAR.' horas antes del partido, '
                        .'y el turno '.$turnoAnterior->textoHorario().' empieza antes de eso.',
                ], 409);
            }

            $nuevoTurno = Turno::find($nuevoTurnoId);

            $motivo = match (true) {
                $nuevoTurno->estado === 'bloqueado' => 'está bloqueado por mantenimiento',
                $nuevoTurno->estado === 'ocupado' => 'ya está reservado',
                $nuevoTurno->yaPaso() => 'ya empezó o terminó',
                default => null,
            };
            if ($motivo) {
                return response()->json([
                    'message' => 'No se puede pasar la reserva al turno '.$nuevoTurno->textoHorario().": {$motivo}. Elegí otro.",
                ], 409);
            }

            $anotadosNuevo = $nuevoTurno->cupoActual();
            if ($request->input('tipo', $reserva->tipo) === 'completa' && $anotadosNuevo > 0) {
                return response()->json([
                    'message' => 'El turno '.$nuevoTurno->textoHorario()." ya tiene {$anotadosNuevo} jugadores anotados por matchmaking: "
                        .'no se puede pasar ahí una reserva de cancha entera.',
                ], 409);
            }

            $cantidad = (int) $request->input('cantidad_jugadores', $reserva->cantidad_jugadores);
            $libresNuevo = $nuevoTurno->cupo_maximo - $anotadosNuevo;
            if ($request->input('tipo', $reserva->tipo) === 'individual' && $cantidad > $libresNuevo) {
                return response()->json([
                    'message' => $this->textoSinCupo($libresNuevo, $cantidad, 'en el turno '.$nuevoTurno->textoHorario()),
                ], 409);
            }
        } elseif ($reserva->tipo === 'individual' && $reserva->estado !== 'cancelada' && $request->has('cantidad_jugadores')) {
            // Mismo turno: los jugadores extra tienen que entrar en el cupo que queda libre
            $extra = (int) $request->input('cantidad_jugadores') - $reserva->cantidad_jugadores;
            $libre = $turnoAnterior->cupo_maximo - $turnoAnterior->cupoActual();

            if ($extra > $libre) {
                return response()->json([
                    'message' => $this->textoSinCupo($libre, $extra, 'en el turno '.$turnoAnterior->textoHorario()),
                ], 409);
            }
        }

        $reserva->update(collect($request->validated())->except('usuario_id')->all());

        // Al cambiar la cantidad de jugadores, el estado o el turno,
        // el cupo puede llenarse o liberarse: se recalculan el turno anterior y el actual
        $turnoAnterior?->recalcularEstado();
        $reserva->fresh()->turno?->recalcularEstado();

        $actualizada = $reserva->fresh();
        $cambioDeTurno = $actualizada->turno_id !== $turnoAnterior?->id;

        return response()->json([
            'message' => $cambioDeTurno
                ? 'Tu reserva pasó al turno '.$actualizada->turno->textoHorario().'. El turno anterior quedó libre.'
                : 'Los cambios de la reserva se guardaron.',
            'reserva' => $actualizada,
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
            new OA\Response(response: 403, description: 'La reserva no es suya ni de un club suyo.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
            new OA\Response(response: 409, description: 'La reserva ya estaba cancelada o está fuera del plazo de cancelación (hasta 2 horas antes del turno).'),
        ]
    )]
    public function destroy(Request $request, string $id)
    {
        $reserva = Reserva::find($id);

        if (! $reserva) {
            return response()->json([
                'message' => "No existe una reserva con el id {$id}.",
            ], 404);
        }

        $turno = $reserva->turno;
        $usuario = $request->user();
        $esAdministradorDelClub = $this->esAdministradorDelClub($usuario, $turno);

        // Cada uno cancela sus reservas; el administrador, las de las canchas de sus clubes
        if (! $esAdministradorDelClub && $reserva->usuario_id !== $usuario->id) {
            return response()->json([
                'message' => 'Solo podés cancelar tus propias reservas o las de tus clubes.',
            ], 403);
        }

        if ($reserva->estado === 'cancelada') {
            return response()->json([
                'message' => 'La reserva ya estaba cancelada.',
            ], 409);
        }

        // El administrador del club puede cancelar siempre; el jugador, solo dentro del plazo
        $limite = $turno?->inicio()->subHours(Reserva::HORAS_MINIMAS_PARA_CANCELAR);
        if (! $esAdministradorDelClub && $limite && now()->greaterThan($limite)) {
            return response()->json([
                'message' => 'Las reservas se pueden cancelar hasta '.Reserva::HORAS_MINIMAS_PARA_CANCELAR.' horas antes del turno, '
                    .'y el turno '.$turno->textoHorario().' empieza antes de eso. Si no podés ir, avisale al club.',
            ], 409);
        }

        $reserva->update(['estado' => 'cancelada']);

        $turno?->recalcularEstado();

        return response()->json([
            'message' => $turno
                ? 'Cancelaste la reserva del turno '.$turno->textoHorario().'. El lugar quedó libre para otros jugadores.'
                : 'Cancelaste la reserva.',
        ], 200);
    }

    // El usuario es el administrador del club al que pertenece la cancha del turno
    private function esAdministradorDelClub(User $usuario, ?Turno $turno): bool
    {
        return $usuario->rol === 'administrador'
            && $turno?->cancha?->club?->administrador_id === $usuario->id;
    }

    // "Queda 1 lugar en el turno ... y querés anotar 3 jugadores."
    private function textoSinCupo(int $libres, int $pedidos, string $donde): string
    {
        $quedan = match ($libres) {
            0 => 'No queda ningún lugar',
            1 => 'Queda 1 lugar',
            default => "Quedan {$libres} lugares",
        };

        return "{$quedan} {$donde} y querés anotar {$pedidos} ".($pedidos === 1 ? 'jugador.' : 'jugadores.');
    }

    /**
     * Reservas que puede ver el usuario: las propias y, si es administrador,
     * las de las canchas de sus clubes. Las reservas tienen nombres y mails.
     */
    private function soloVisiblesPara($query, User $usuario)
    {
        return $query->where(function ($q) use ($usuario) {
            $q->where('usuario_id', $usuario->id);

            if ($usuario->rol === 'administrador') {
                $q->orWhereHas('turno.cancha.club', fn ($c) => $c->where('administrador_id', $usuario->id));
            }
        });
    }
}
