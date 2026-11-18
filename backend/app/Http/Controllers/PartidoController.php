<?php

namespace App\Http\Controllers;

use App\Models\Reserva;
use App\Models\Turno;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Lobby de matchmaking: los partidos que se están armando con jugadores sueltos.
 * El listado es público y no expone quiénes están anotados (solo cuántos);
 * la sala de cada partido solo la ven los anotados y el dueño del club.
 */
class PartidoController extends Controller
{
    #[OA\Get(
        path: '/api/partidos-abiertos',
        summary: 'Partidos abiertos (lobby de matchmaking)',
        description: 'Turnos que todavía no empezaron, con al menos un jugador anotado por matchmaking y lugares libres. Ordenados por los que menos les falta, para que se completen primero. No incluye datos de los jugadores.',
        tags: ['Matchmaking'],
        parameters: [
            new OA\Parameter(name: 'club_id', description: 'Solo los partidos de un club', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'fecha', description: 'Solo los partidos de un día (AAAA-MM-DD)', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Listado de partidos abiertos.', content: new OA\JsonContent(type: 'array', items: new OA\Items(properties: [
                new OA\Property(property: 'turno_id', type: 'integer', example: 42),
                new OA\Property(property: 'fecha', type: 'string', format: 'date', example: '2026-11-20'),
                new OA\Property(property: 'hora_inicio', type: 'string', example: '20:00'),
                new OA\Property(property: 'hora_fin', type: 'string', example: '21:00'),
                new OA\Property(property: 'cupo_maximo', type: 'integer', example: 5),
                new OA\Property(property: 'anotados', type: 'integer', example: 3),
                new OA\Property(property: 'faltan', type: 'integer', example: 2),
                new OA\Property(property: 'precio_por_jugador', type: 'number', example: 3400),
                new OA\Property(property: 'cancha', type: 'object', example: ['id' => 1, 'nombre' => 'Cancha 1', 'tipo' => 'futbol5', 'superficie' => 'cesped_sintetico']),
                new OA\Property(property: 'club', type: 'object', example: ['id' => 1, 'nombre' => 'Complejo Concordia FC', 'direccion' => 'Av. Costanera 1200']),
            ]))),
            new OA\Response(response: 422, description: 'La fecha no tiene el formato AAAA-MM-DD.'),
        ]
    )]
    public function abiertos(Request $request)
    {
        $request->validate(
            ['fecha' => ['nullable', 'date_format:Y-m-d'], 'club_id' => ['nullable', 'integer']],
            ['fecha.date_format' => 'La fecha tiene que tener el formato AAAA-MM-DD (por ejemplo 2026-12-10).']
        );

        $turnos = Turno::query()
            ->where('estado', 'disponible')
            ->whereDate('fecha', '>=', now()->toDateString())
            ->when($request->query('fecha'), fn ($q, $fecha) => $q->whereDate('fecha', $fecha))
            ->whereHas('cancha', fn ($q) => $q->where('estado', 'activa')
                ->when($request->query('club_id'), fn ($c, $club) => $c->where('club_id', $club)))
            ->whereHas('reservas', fn ($q) => $q->where('tipo', 'individual')->where('estado', '!=', 'cancelada'))
            ->withSum(['reservas as anotados' => fn ($q) => $q->where('tipo', 'individual')->where('estado', '!=', 'cancelada')], 'cantidad_jugadores')
            ->with('cancha:id,club_id,nombre,tipo,superficie,tarifa', 'cancha.club:id,nombre,direccion')
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->limit(100)
            ->get();

        $partidos = $turnos
            ->filter(fn (Turno $t) => ! $t->yaPaso() && (int) $t->anotados < $t->cupo_maximo)
            ->map(fn (Turno $t) => [
                'turno_id' => $t->id,
                'fecha' => substr($t->fecha, 0, 10),
                'hora_inicio' => substr($t->hora_inicio, 0, 5),
                'hora_fin' => substr($t->hora_fin, 0, 5),
                'cupo_maximo' => $t->cupo_maximo,
                'anotados' => (int) $t->anotados,
                'faltan' => $t->cupo_maximo - (int) $t->anotados,
                'precio_por_jugador' => round((float) $t->cancha->tarifa / max(1, $t->cupo_maximo), 2),
                'cancha' => $t->cancha->only('id', 'nombre', 'tipo', 'superficie'),
                'club' => $t->cancha->club?->only('id', 'nombre', 'direccion'),
            ])
            // Primero los que están por completarse
            ->sortBy([['faltan', 'asc'], ['fecha', 'asc'], ['hora_inicio', 'asc']])
            ->values();

        return response()->json($partidos, 200);
    }

    #[OA\Get(
        path: '/api/partidos/{id}',
        summary: 'Sala de un partido de matchmaking',
        description: 'Datos del partido, cuántos lugares faltan y quiénes se anotaron (nombre y apellido abreviado, sin mails). Solo la ven los jugadores anotados y el administrador del club. Incluye la reserva propia y si todavía se puede cancelar.',
        security: [['sanctum' => []]],
        tags: ['Matchmaking'],
        parameters: [
            new OA\Parameter(name: 'id', description: 'Id del turno', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sala del partido.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'turno_id', type: 'integer', example: 42),
                new OA\Property(property: 'fecha', type: 'string', format: 'date', example: '2026-11-20'),
                new OA\Property(property: 'hora_inicio', type: 'string', example: '20:00'),
                new OA\Property(property: 'hora_fin', type: 'string', example: '21:00'),
                new OA\Property(property: 'estado', type: 'string', enum: ['armandose', 'completo', 'ya_empezo'], example: 'armandose'),
                new OA\Property(property: 'cupo_maximo', type: 'integer', example: 5),
                new OA\Property(property: 'anotados', type: 'integer', example: 3),
                new OA\Property(property: 'faltan', type: 'integer', example: 2),
                new OA\Property(property: 'precio_por_jugador', type: 'number', example: 3400),
                new OA\Property(property: 'cancha', type: 'object', example: ['id' => 1, 'nombre' => 'Cancha 1', 'tipo' => 'futbol5', 'superficie' => 'cesped_sintetico', 'techada' => true, 'iluminacion' => true, 'vestuarios' => false]),
                new OA\Property(property: 'club', type: 'object', example: ['id' => 1, 'nombre' => 'Complejo Concordia FC', 'direccion' => 'Av. Costanera 1200', 'telefono' => '3454111111']),
                new OA\Property(property: 'jugadores', type: 'array', items: new OA\Items(properties: [
                    new OA\Property(property: 'nombre', type: 'string', example: 'Lautaro M.'),
                    new OA\Property(property: 'cantidad', type: 'integer', example: 2, description: 'Lugares que ocupa (él y sus amigos)'),
                    new OA\Property(property: 'soy_yo', type: 'boolean', example: false),
                ])),
                new OA\Property(property: 'mi_reserva', type: 'object', nullable: true, example: ['id' => 15, 'cantidad_jugadores' => 1, 'puede_cancelar' => true, 'cancelar_hasta' => '2026-11-20 18:00']),
            ])),
            new OA\Response(response: 401, description: 'Falta iniciar sesión.'),
            new OA\Response(response: 403, description: 'No estás anotado en este partido ni sos el administrador del club.'),
            new OA\Response(response: 404, description: 'No existe el turno o no tiene un partido de matchmaking.'),
        ]
    )]
    public function show(Request $request, string $id)
    {
        $turno = Turno::with([
            'cancha:id,club_id,nombre,tipo,superficie,techada,iluminacion,vestuarios,tarifa',
            'cancha.club:id,administrador_id,nombre,direccion,telefono',
            'reservas' => fn ($q) => $q->where('tipo', 'individual')->where('estado', '!=', 'cancelada')->orderBy('created_at'),
            'reservas.usuario:id,name',
        ])->find($id);

        if (! $turno) {
            return response()->json(['message' => "No existe un turno con el id {$id}."], 404);
        }

        if ($turno->reservas->isEmpty()) {
            return response()->json([
                'message' => 'El turno '.$turno->textoHorario().' no tiene un partido de matchmaking: nadie se anotó suelto.',
            ], 404);
        }

        $usuario = $request->user();
        $miReserva = $turno->reservas->firstWhere('usuario_id', $usuario->id);
        $esAdministradorDelClub = $usuario->rol === 'administrador'
            && $turno->cancha?->club?->administrador_id === $usuario->id;

        // Los nombres de los anotados solo los ven los que juegan y el dueño del club
        if (! $miReserva && ! $esAdministradorDelClub) {
            return response()->json([
                'message' => 'Solo los jugadores anotados pueden ver la sala del partido '.$turno->textoHorario().'. Sumate desde la grilla de la cancha.',
            ], 403);
        }

        $anotados = (int) $turno->reservas->sum('cantidad_jugadores');
        $limiteCancelacion = $turno->inicio()->subHours(Reserva::HORAS_MINIMAS_PARA_CANCELAR);

        return response()->json([
            'turno_id' => $turno->id,
            'fecha' => substr($turno->fecha, 0, 10),
            'hora_inicio' => substr($turno->hora_inicio, 0, 5),
            'hora_fin' => substr($turno->hora_fin, 0, 5),
            'estado' => match (true) {
                $turno->yaPaso() => 'ya_empezo',
                $anotados >= $turno->cupo_maximo => 'completo',
                default => 'armandose',
            },
            'cupo_maximo' => $turno->cupo_maximo,
            'anotados' => $anotados,
            'faltan' => max(0, $turno->cupo_maximo - $anotados),
            'precio_por_jugador' => round((float) $turno->cancha->tarifa / max(1, $turno->cupo_maximo), 2),
            'cancha' => $turno->cancha->only('id', 'nombre', 'tipo', 'superficie', 'techada', 'iluminacion', 'vestuarios'),
            'club' => $turno->cancha->club?->only('id', 'nombre', 'direccion', 'telefono'),
            'jugadores' => $turno->reservas->map(fn (Reserva $r) => [
                'nombre' => $this->nombreCorto($r->usuario?->name),
                'cantidad' => $r->cantidad_jugadores,
                'soy_yo' => $r->usuario_id === $usuario->id,
            ])->values(),
            'mi_reserva' => $miReserva ? [
                'id' => $miReserva->id,
                'cantidad_jugadores' => $miReserva->cantidad_jugadores,
                'puede_cancelar' => now()->lessThanOrEqualTo($limiteCancelacion),
                'cancelar_hasta' => $limiteCancelacion->format('Y-m-d H:i'),
            ] : null,
        ], 200);
    }

    // "Lautaro Meza" -> "Lautaro M.": alcanza para reconocerse sin mostrar el nombre completo
    private function nombreCorto(?string $nombre): string
    {
        $partes = preg_split('/\s+/', trim((string) $nombre)) ?: [];
        if (! $partes || $partes[0] === '') {
            return 'Jugador';
        }

        return count($partes) > 1
            ? $partes[0].' '.mb_strtoupper(mb_substr(end($partes), 0, 1)).'.'
            : $partes[0];
    }
}
