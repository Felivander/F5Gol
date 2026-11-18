<?php

namespace Database\Seeders;

use App\Models\JugadorAnotado;
use App\Models\Reserva;
use Illuminate\Database\Seeder;

class JugadorAnotadoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Registra el detalle de cada jugador anotado individualmente
     * (matchmaking) dentro de sus reservas.
     */
    public function run(): void
    {
        // Solo los partidos de ejemplo de hoy en adelante (no los del historial de demo)
        $reservasIndividuales = Reserva::where('tipo', 'individual')
            ->whereHas('turno', fn ($q) => $q->whereDate('fecha', '>=', now()->toDateString()))
            ->get();

        foreach ($reservasIndividuales as $reserva) {
            JugadorAnotado::firstOrCreate([
                'reserva_id' => $reserva->id,
                'usuario_id' => $reserva->usuario_id,
            ], [
                'fecha_inscripcion' => $reserva->created_at,
            ]);
        }
    }
}
