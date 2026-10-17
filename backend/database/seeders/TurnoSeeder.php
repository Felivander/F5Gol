<?php

namespace Database\Seeders;

use App\Models\Cancha;
use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class TurnoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Genera la grilla de horarios (15:00 a 22:00) para los próximos 2 días,
     * para cada cancha activa. Se puede correr más de una vez: no duplica turnos.
     */
    public function run(): void
    {
        $canchas = Cancha::where('estado', 'activa')->get();
        $horaInicioDia = 15; // 3PM
        $horaFinDia = 22; // 10PM

        foreach ($canchas as $cancha) {
            for ($dia = 0; $dia < 2; $dia++) {
                $fecha = Carbon::now()->addDays($dia)->toDateString();

                for ($hora = $horaInicioDia; $hora < $horaFinDia; $hora++) {
                    Turno::firstOrCreate(
                        [
                            'cancha_id' => $cancha->id,
                            'fecha' => $fecha,
                            'hora_inicio' => sprintf('%02d:00:00', $hora),
                        ],
                        [
                            'hora_fin' => sprintf('%02d:00:00', $hora + 1),
                            'estado' => 'disponible',
                            // Igual que el default de la tabla (5 en fútbol 5): la mitad de la capacidad
                            'cupo_maximo' => intdiv($cancha->capacidad_jugadores, 2),
                        ]
                    );
                }
            }
        }
    }
}
