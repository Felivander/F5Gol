<?php

namespace App\Http\Controllers;

use App\Models\JugadorAnotado;
use Illuminate\Http\Request;

class JugadorAnotadoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = JugadorAnotado::with(['usuario:id,name,email', 'reserva.turno'])
            ->orderBy('fecha_inscripcion');

        if ($request->has('reserva_id')) {
            $query->where('reserva_id', $request->query('reserva_id'));
        }

        return response()->json($query->get(), 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $jugadorAnotado = JugadorAnotado::create($request->all());

        return response()->json([
            'message' => 'Jugador anotado correctamente',
            'jugador_anotado' => $jugadorAnotado,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $jugadorAnotado = JugadorAnotado::with(['usuario', 'reserva'])->findOrFail($id);

        return response()->json($jugadorAnotado, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $jugadorAnotado = JugadorAnotado::find($id);

        if (! $jugadorAnotado) {
            return response()->json([
                'message' => 'Jugador anotado no encontrado',
            ], 404);
        }

        $jugadorAnotado->update($request->all());

        return response()->json([
            'message' => 'Jugador anotado actualizado correctamente',
            'jugador_anotado' => $jugadorAnotado->fresh(),
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $jugadorAnotado = JugadorAnotado::find($id);

        if (! $jugadorAnotado) {
            return response()->json([
                'message' => 'Jugador anotado no encontrado',
            ], 404);
        }

        $jugadorAnotado->delete();

        return response()->json([
            'message' => 'Jugador anotado eliminado correctamente',
        ], 200);
    }
}
