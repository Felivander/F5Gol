<?php

namespace App\Http\Controllers;

use App\Models\Reserva;
use App\Models\Turno;
use App\Http\Requests\ReservaRequest;
use Illuminate\Http\Request;
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
            new OA\Parameter(name: 'page', description: 'Número de página', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Listado paginado (10 por página).'),
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

        return response()->json($query->paginate(10), 200);
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
            new OA\Response(response: 422, description: 'Datos inválidos: devuelve un mensaje por cada campo con error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 404, description: 'Turno no encontrado.'),
            new OA\Response(response: 409, description: 'Turno no disponible o sin cupo suficiente.'),
        ]
    )]
    public function store(ReservaRequest $request)
    {
        $turno = Turno::find($request->input('turno_id'));

        if (! $turno) {
            return response()->json([
                'message' => 'Turno no encontrado',
            ], 404);
        }

        if (in_array($turno->estado, ['ocupado', 'bloqueado'])) {
            return response()->json([
                'message' => 'El turno ya no está disponible',
            ], 409);
        }

        $tipo = $request->input('tipo', 'completa');
        $cantidad = (int) $request->input('cantidad_jugadores', 1);

        if ($tipo === 'individual') {
            $cupoDisponible = $turno->cupo_maximo - $turno->cupoActual();

            if ($cantidad > $cupoDisponible) {
                return response()->json([
                    'message' => 'No hay cupo suficiente en este turno',
                ], 409);
            }
        }

        $reserva = Reserva::create(array_merge($request->validated(), [
            'estado' => 'confirmada',
        ]));

        if ($tipo === 'completa') {
            $turno->update(['estado' => 'ocupado']);
        } elseif ($turno->cupoActual() >= $turno->cupo_maximo) {
            // Se completó el cupo de matchmaking: el turno queda confirmado
            $turno->update(['estado' => 'ocupado']);
        }

        return response()->json([
            'message' => 'Reserva creada correctamente',
            'reserva' => $reserva->fresh(),
        ], 201);
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
            new OA\Response(response: 404, description: 'No encontrado.'),
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

        $reserva->update($request->validated());

        return response()->json([
            'message' => 'Reserva actualizada correctamente',
            'reserva' => $reserva->fresh(),
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * Cancelar una reserva libera el turno automáticamente.
     */
    #[OA\Delete(
        path: '/api/reservas/{id}',
        summary: 'Eliminar reserva',
        tags: ['Reservas'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/id')],
        responses: [
            new OA\Response(response: 200, description: 'Eliminado correctamente.', content: new OA\JsonContent(ref: '#/components/schemas/Mensaje')),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
            new OA\Response(response: 409, description: 'Fuera del plazo de cancelación (hasta 2 horas antes del turno).'),
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

        $turno = $reserva->turno;

        // El administrador del club puede cancelar siempre; el jugador, solo dentro del plazo
        $limite = $turno?->inicio()->subHours(Reserva::HORAS_MINIMAS_PARA_CANCELAR);
        if ($request->user()->rol !== 'administrador' && $limite && now()->greaterThan($limite)) {
            return response()->json([
                'message' => 'Las reservas se pueden cancelar hasta '.Reserva::HORAS_MINIMAS_PARA_CANCELAR.' horas antes del turno.',
            ], 409);
        }

        $reserva->delete();

        if ($turno && $turno->estado !== 'bloqueado') {
            // El turno sigue ocupado solo si queda una reserva completa activa
            // o si los jugadores de matchmaking todavía llenan el cupo.
            $hayCompleta = $turno->reservas()
                ->where('tipo', 'completa')
                ->where('estado', '!=', 'cancelada')
                ->exists();
            $cupoLleno = $turno->cupoActual() >= $turno->cupo_maximo;

            $turno->update(['estado' => $hayCompleta || $cupoLleno ? 'ocupado' : 'disponible']);
        }

        return response()->json([
            'message' => 'Reserva cancelada correctamente',
        ], 200);
    }
}
