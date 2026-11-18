<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El partido de matchmaking pasa a tener dos equipos (A y B).
 * Cada reserva de partido dice en qué equipo juega, y el cupo del turno
 * pasa a ser el de los dos equipos juntos (la capacidad de la cancha).
 * (El valor por defecto de cupo_maximo lo pone el modelo Turno: cambiar la columna
 * rehace la tabla en SQLite y rompe las vistas de Power BI.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->string('equipo', 1)->nullable()->after('cantidad_jugadores');
        });

        // Los anotados que ya había quedan en el Equipo A
        DB::table('reservas')->where('tipo', 'individual')->update(['equipo' => 'A']);

        // Turnos cargados con el cupo de un solo equipo (la mitad de la cancha): pasan al cupo de los dos
        foreach (DB::table('turnos')->join('canchas', 'canchas.id', '=', 'turnos.cancha_id')
            ->whereRaw('turnos.cupo_maximo * 2 = canchas.capacidad_jugadores')
            ->pluck('canchas.capacidad_jugadores', 'turnos.id') as $turnoId => $capacidad) {
            DB::table('turnos')->where('id', $turnoId)->update(['cupo_maximo' => $capacidad]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('turnos')->join('canchas', 'canchas.id', '=', 'turnos.cancha_id')
            ->whereColumn('turnos.cupo_maximo', 'canchas.capacidad_jugadores')
            ->pluck('canchas.capacidad_jugadores', 'turnos.id') as $turnoId => $capacidad) {
            DB::table('turnos')->where('id', $turnoId)->update(['cupo_maximo' => intdiv($capacidad, 2)]);
        }

        DB::table('reservas')->where('tipo', 'equipo')->update(['tipo' => 'individual']);

        Schema::table('reservas', function (Blueprint $table) {
            $table->dropColumn('equipo');
        });
    }
};
