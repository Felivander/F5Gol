<?php

namespace App\Http\Controllers;

use App\Models\Reserva;
use App\Models\Turno;
use Illuminate\Http\Request;

class ReservaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * "Mis reservas": /api/reservas?usuario_id=1
     */
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

        return response()->json($query->paginate(10), 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * Reserva completa: ocupa el turno entero.
     * Reserva individual (matchmaking): suma jugadores al cupo del turno
     * y solo lo marca "ocupado" cuando se completa el cupo_maximo.
     */
    public function store(Request $request)
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

        $reserva = Reserva::create(array_merge($request->all(), [
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
    public function show(string $id)
    {
        $reserva = Reserva::with(['turno.cancha', 'usuario:id,name,email', 'jugadorAnotados.usuario:id,name'])
            ->findOrFail($id);

        return response()->json($reserva, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $reserva = Reserva::find($id);

        if (! $reserva) {
            return response()->json([
                'message' => 'Reserva no encontrada',
            ], 404);
        }

        $reserva->update($request->all());

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
    public function destroy(string $id)
    {
        $reserva = Reserva::find($id);

        if (! $reserva) {
            return response()->json([
                'message' => 'Reserva no encontrada',
            ], 404);
        }

        $turno = $reserva->turno;
        $reserva->delete();

        if ($turno) {
            // Si ya no queda ningún jugador anotado (matchmaking) ni reserva
            // completa activa, el turno vuelve a estar disponible.
            $sigueOcupado = $turno->reservas()->where('estado', '!=', 'cancelada')->exists();
            $turno->update(['estado' => $sigueOcupado ? $turno->estado : 'disponible']);
        }

        return response()->json([
            'message' => 'Reserva cancelada correctamente',
        ], 200);
    }
}
