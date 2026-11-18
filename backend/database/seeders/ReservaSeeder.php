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
        // Solo jugadores de prueba: nunca se cargan reservas a cuentas reales
        $jugadores = User::where('rol', 'jugador')->where('email', 'like', '%@f5gol.com')->orderBy('id')->get();

        $primeraCancha = Cancha::orderBy('id')->first();
        // Turnos de hoy en adelante (los pasados son del historial de demo)
        $turnos = Turno::where('cancha_id', $primeraCancha->id)
            ->whereDate('fecha', '>=', now()->toDateString())
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get();

        // Si los escenarios de ejemplo ya están cargados, no se repiten
        if ($turnos->isEmpty() || Reserva::whereIn('turno_id', $turnos->take(2)->pluck('id'))->exists()) {
            return;
        }

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

        // Escenario 2: matchmaking con cupo incompleto (3 de 10 jugadores)
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
        // El turno permanece "disponible": el cupo (3/10) todavía no se completó
    }
}
