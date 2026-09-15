<?php

namespace App\Http\Controllers;

use App\Models\Club;
use Illuminate\Http\Request;

class ClubController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $clubs = Club::with('administrador:id,name,email')
            ->orderBy('nombre')
            ->paginate(10);

        return response()->json($clubs, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $club = Club::create($request->all());

        return response()->json([
            'message' => 'Club creado correctamente',
            'club' => $club,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $club = Club::with('canchas')->findOrFail($id);

        return response()->json($club, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $club = Club::find($id);

        if (! $club) {
            return response()->json([
                'message' => 'Club no encontrado',
            ], 404);
        }

        $club->update($request->all());

        return response()->json([
            'message' => 'Club actualizado correctamente',
            'club' => $club->fresh(),
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $club = Club::find($id);

        if (! $club) {
            return response()->json([
                'message' => 'Club no encontrado',
            ], 404);
        }

        $club->delete();

        return response()->json([
            'message' => 'Club eliminado correctamente',
        ], 200);
    }
}
