<?php

namespace Database\Seeders;

use App\Models\Cancha;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Historial de demostración para el dashboard de Power BI: las últimas 8 semanas
 * de turnos y reservas de cada cancha activa, más jugadores registrados en ese período.
 *
 * Los números siguen un patrón creíble: más demanda de 19 a 23 hs y los viernes y sábados,
 * algunas cancelaciones y partidos armados por matchmaking. Usa una semilla fija,
 * así todos los integrantes ven el mismo dashboard.
 *
 * Solo toca fechas pasadas (los turnos de hoy en adelante son los de TurnoSeeder)
 * y se puede correr una sola vez: si ya están los jugadores de demo, no hace nada.
 */
class HistorialDemoSeeder extends Seeder
{
    private const SEMANAS = 8;
    private const JUGADORES_DEMO = 30;
    private const HORA_DESDE = 15;
    private const HORA_HASTA = 23;

    public function run(): void
    {
        if (User::where('email', 'demo1@f5gol.com')->exists()) {
            $this->command?->info('El historial de demo ya estaba cargado.');
            return;
        }

        mt_srand(2026);
        $hoy = Carbon::today();
        $inicio = $hoy->copy()->subWeeks(self::SEMANAS);

        $jugadores = $this->crearJugadores($inicio, $hoy);

        $canchas = Cancha::where('estado', 'activa')->get();

        foreach ($canchas as $cancha) {
            $this->cargarCancha($cancha, $jugadores, $inicio, $hoy);
        }
    }

    /** @return list<int> ids de jugadores para asignar reservas */
    private function crearJugadores(Carbon $inicio, Carbon $hoy): array
    {
        $nombres = ['Mateo', 'Santiago', 'Benjamín', 'Thiago', 'Lucas', 'Joaquín', 'Valentino', 'Franco',
            'Tomás', 'Bautista', 'Facundo', 'Lautaro', 'Ignacio', 'Gonzalo', 'Nicolás', 'Martina',
            'Camila', 'Sofía', 'Julieta', 'Agustina', 'Ramiro', 'Emiliano', 'Diego', 'Federico',
            'Lionel', 'Gastón', 'Hernán', 'Matías', 'Leandro', 'Ezequiel'];
        $apellidos = ['Ríos', 'Benítez', 'Acosta', 'Ojeda', 'Romero', 'Ferreyra', 'Sosa', 'Godoy', 'Molina', 'Ledesma'];
        $clave = Hash::make('password');
        $dias = $inicio->diffInDays($hoy);
        $filas = [];

        for ($i = 1; $i <= self::JUGADORES_DEMO; $i++) {
            // Más altas en las últimas semanas: la app va ganando usuarios
            $creado = $inicio->copy()->addDays((int) round($dias * sqrt(mt_rand(0, 1000) / 1000)))->setTime(mt_rand(9, 22), mt_rand(0, 59));
            $filas[] = [
                'name' => $nombres[$i - 1].' '.$apellidos[mt_rand(0, count($apellidos) - 1)],
                'email' => "demo{$i}@f5gol.com",
                'password' => $clave,
                'rol' => 'jugador',
                // Un tercio se registra con Google (identificador ficticio, solo para el dashboard)
                'google_id' => mt_rand(1, 3) === 1 ? "demo-google-{$i}" : null,
                'created_at' => $creado,
                'updated_at' => $creado,
            ];
        }

        DB::table('users')->insert($filas);

        // Solo jugadores de prueba (@f5gol.com): nunca se cargan reservas de demo a cuentas reales
        return User::where('rol', 'jugador')->where('email', 'like', '%@f5gol.com')->pluck('id')->all();
    }

    private function cargarCancha(Cancha $cancha, array $jugadores, Carbon $inicio, Carbon $hoy): void
    {
        $cupo = max(1, intdiv($cancha->capacidad_jugadores, 2));

        // Turnos que ya existen en el período, para no repetirlos (una sola consulta)
        $existentes = DB::table('turnos')
            ->where('cancha_id', $cancha->id)
            ->whereBetween('fecha', [$inicio->toDateString(), $hoy->toDateString()])
            ->get(['fecha', 'hora_inicio'])
            ->map(fn ($t) => substr($t->fecha, 0, 10).' '.substr($t->hora_inicio, 0, 5))
            ->flip();

        for ($fecha = $inicio->copy(); $fecha->lt($hoy); $fecha->addDay()) {
            for ($hora = self::HORA_DESDE; $hora < self::HORA_HASTA; $hora++) {
                if ($existentes->has($fecha->toDateString().' '.sprintf('%02d:00', $hora))) {
                    continue;
                }

                $turnoId = DB::table('turnos')->insertGetId([
                    'cancha_id' => $cancha->id,
                    'fecha' => $fecha->toDateString(),
                    'hora_inicio' => sprintf('%02d:00:00', $hora),
                    'hora_fin' => sprintf('%02d:00:00', $hora + 1),
                    'estado' => 'disponible',
                    'cupo_maximo' => $cupo,
                    'created_at' => $inicio,
                    'updated_at' => $inicio,
                ]);

                if (mt_rand(1, 1000) / 1000 > $this->probabilidadDeReserva($fecha, $hora)) {
                    continue;
                }

                $estadoTurno = mt_rand(1, 100) <= 75
                    ? $this->reservaCompleta($turnoId, $jugadores, $fecha, $hora)
                    : $this->partidoMatchmaking($turnoId, $jugadores, $fecha, $hora, $cupo);

                DB::table('turnos')->where('id', $turnoId)->update(['estado' => $estadoTurno]);
            }
        }
    }

    // Más demanda a la noche y hacia el fin de semana
    private function probabilidadDeReserva(Carbon $fecha, int $hora): float
    {
        $base = match (true) {
            $hora >= 20 => 0.70,
            $hora >= 18 => 0.50,
            default => 0.20,
        };

        $extra = match ($fecha->dayOfWeek) {
            Carbon::FRIDAY, Carbon::SATURDAY => 0.15,
            Carbon::SUNDAY => $hora < 19 ? 0.15 : -0.10,
            Carbon::MONDAY => -0.10,
            default => 0.0,
        };

        return min(0.95, max(0.05, $base + $extra));
    }

    private function reservaCompleta(int $turnoId, array $jugadores, Carbon $fecha, int $hora): string
    {
        $cancelada = mt_rand(1, 100) <= 8;

        DB::table('reservas')->insert($this->fila($turnoId, $jugadores, $fecha, $hora, 'completa', 10, $cancelada));

        return $cancelada ? 'disponible' : 'ocupado';
    }

    // Jugadores sueltos que se anotan; a veces completan el cupo y a veces no
    private function partidoMatchmaking(int $turnoId, array $jugadores, Carbon $fecha, int $hora, int $cupo): string
    {
        $anotados = mt_rand(1, 100) <= 70 ? $cupo : mt_rand(1, max(1, $cupo - 1));
        $filas = [];

        foreach ((array) array_rand(array_flip($jugadores), min($anotados, count($jugadores))) as $usuarioId) {
            $filas[] = $this->fila($turnoId, [$usuarioId], $fecha, $hora, 'individual', 1, false);
        }

        DB::table('reservas')->insert($filas);

        return $anotados >= $cupo ? 'ocupado' : 'disponible';
    }

    private function fila(int $turnoId, array $jugadores, Carbon $fecha, int $hora, string $tipo, int $cantidad, bool $cancelada): array
    {
        // Se reserva entre 0 y 5 días antes del partido
        $reservada = $fecha->copy()->setTime($hora, 0)->subHours(mt_rand(2, 120));
        $sorteo = mt_rand(1, 100);

        return [
            'turno_id' => $turnoId,
            'usuario_id' => $jugadores[array_rand($jugadores)],
            'tipo' => $tipo,
            'cantidad_jugadores' => $cantidad,
            'estado' => $cancelada ? 'cancelada' : 'confirmada',
            'estado_pago' => $cancelada ? 'pendiente' : ($sorteo <= 80 ? 'pagado' : ($sorteo <= 95 ? 'sena' : 'pendiente')),
            'created_at' => $reservada,
            'updated_at' => $reservada,
        ];
    }
}
