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
        $reservasIndividuales = Reserva::where('tipo', 'individual')->get();

        foreach ($reservasIndividuales as $reserva) {
            JugadorAnotado::create([
                'reserva_id' => $reserva->id,
                'usuario_id' => $reserva->usuario_id,
                'fecha_inscripcion' => $reserva->created_at,
            ]);
        }
    }
}
