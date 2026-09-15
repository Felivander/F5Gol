<?php

namespace App\Http\Controllers;

use App\Models\Turno;
use Illuminate\Http\Request;

class TurnoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * Grilla de disponibilidad: /api/turnos?cancha_id=1&fecha=2026-09-10
     */
    public function index(Request $request)
    {
        $query = Turno::with('cancha:id,nombre,club_id')->orderBy('hora_inicio');

        if ($request->has('cancha_id')) {
            $query->where('cancha_id', $request->query('cancha_id'));
        }

        if ($request->has('fecha')) {
            $query->where('fecha', $request->query('fecha'));
        }

        $turnos = $query->get()->map(function (Turno $turno) {
            $turno->cupo_actual = $turno->cupoActual();

            return $turno;
        });

        return response()->json($turnos, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $turno = Turno::create($request->all());

        return response()->json([
            'message' => 'Turno creado correctamente',
            'turno' => $turno,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $turno = Turno::with('cancha')->findOrFail($id);
        $turno->cupo_actual = $turno->cupoActual();

        return response()->json($turno, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $turno = Turno::find($id);

        if (! $turno) {
            return response()->json([
                'message' => 'Turno no encontrado',
            ], 404);
        }

        $turno->update($request->all());

        return response()->json([
            'message' => 'Turno actualizado correctamente',
            'turno' => $turno->fresh(),
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $turno = Turno::find($id);

        if (! $turno) {
            return response()->json([
                'message' => 'Turno no encontrado',
            ], 404);
        }

        $turno->delete();

        return response()->json([
            'message' => 'Turno eliminado correctamente',
        ], 200);
    }
}
