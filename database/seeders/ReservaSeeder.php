<?php

namespace Database\Seeders;

use App\Models\Cancha;
use App\Models\Reserva;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReservaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Deja cargados dos escenarios de ejemplo:
     * - Un turno reservado de forma completa.
     * - Un turno en matchmaking con cupo incompleto (nunca debe verse "Ocupado").
     */
    public function run(): void
    {
        $jugadores = User::where('rol', 'jugador')->orderBy('id')->get();

        $primeraCancha = Cancha::orderBy('id')->first();
        $turnos = Turno::where('cancha_id', $primeraCancha->id)
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get();

        // Escenario 1: reserva completa del primer turno disponible
        $turnoCompleto = $turnos[0];
        Reserva::create([
            'turno_id' => $turnoCompleto->id,
            'usuario_id' => $jugadores[0]->id,
            'tipo' => 'completa',
            'cantidad_jugadores' => 10,
            'estado' => 'confirmada',
            'estado_pago' => 'sena',
        ]);
        $turnoCompleto->update(['estado' => 'ocupado']);

        // Escenario 2: matchmaking con cupo incompleto (3 de 5 jugadores)
        $turnoMatchmaking = $turnos[1];
        for ($i = 1; $i <= 3; $i++) {
            Reserva::create([
                'turno_id' => $turnoMatchmaking->id,
                'usuario_id' => $jugadores[$i]->id,
                'tipo' => 'individual',
                'cantidad_jugadores' => 1,
                'estado' => 'confirmada',
                'estado_pago' => 'pendiente',
            ]);
        }
        // El turno permanece "disponible": el cupo (3/5) todavía no se completó
    }
}
