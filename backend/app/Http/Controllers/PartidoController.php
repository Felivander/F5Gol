<?php

namespace App\Http\Controllers;

use App\Models\Turno;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Lobby de matchmaking: los partidos que se están armando con jugadores sueltos.
 * Es público y no expone quiénes están anotados (solo cuántos).
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
}
