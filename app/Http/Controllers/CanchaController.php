<?php

namespace App\Http\Controllers;

use App\Models\Cancha;
use Illuminate\Http\Request;

class CanchaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Cancha::with('club:id,nombre')->orderBy('nombre');

        // Permite filtrar por club: /api/canchas?club_id=1
        if ($request->has('club_id')) {
            $query->where('club_id', $request->query('club_id'));
        }

        return response()->json($query->paginate(10), 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $cancha = Cancha::create($request->all());

        return response()->json([
            'message' => 'Cancha creada correctamente',
            'cancha' => $cancha,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $cancha = Cancha::with('club')->findOrFail($id);

        return response()->json($cancha, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $cancha = Cancha::find($id);

        if (! $cancha) {
            return response()->json([
                'message' => 'Cancha no encontrada',
            ], 404);
        }

        $cancha->update($request->all());

        return response()->json([
            'message' => 'Cancha actualizada correctamente',
            'cancha' => $cancha->fresh(),
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $cancha = Cancha::find($id);

        if (! $cancha) {
            return response()->json([
                'message' => 'Cancha no encontrada',
            ], 404);
        }

        $cancha->delete();

        return response()->json([
            'message' => 'Cancha eliminada correctamente',
        ], 200);
    }
}
