<?php

namespace Database\Seeders;

use App\Models\JugadorAnotado;
use App\Models\Reserva;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class PartidosCasiLlenosSeeder extends Seeder
{
    // Cuántos partidos a un lugar de completarse deja listos para la demo
    public const CANTIDAD = 5;

    /**
     * Run the database seeds.
     *
     * Para la demo: deja 5 partidos de matchmaking a los que les falta un solo jugador.
     * Cuando alguien se suma, el partido llega a su cupo, pasa a "ocupado" y se dispara
     * el aviso de partido completo (el flujo de n8n o, si n8n no responde, el mail de Laravel).
     *
     * Usa turnos que empiezan dentro de al menos una hora, así que conviene correrlo el
     * mismo día de la presentación (después de TurnoSeeder). Se puede correr más de una vez:
     * si ya hay 5 partidos a un lugar de completarse, no agrega más.
     */
    public function run(): void
    {
        $faltan = self::CANTIDAD - $this->partidosCasiLlenos()->count();

        if ($faltan <= 0) {
            $this->command?->info('Ya hay '.self::CANTIDAD.' partidos a un lugar de completarse.');

            return;
        }

        $turnos = $this->turnosLibres($faltan);
        $jugadores = $this->jugadores();

        if ($turnos->isEmpty() || $jugadores->isEmpty()) {
            $this->command?->warn('No hay turnos libres de acá a mañana para armar partidos casi llenos. Corré antes TurnoSeeder.');

            return;
        }

        $siguiente = 0;
        foreach ($turnos->values() as $i => $turno) {
            // Se alterna qué equipo queda con el lugar libre, para que en la demo se vean los dos casos
            $equipoIncompleto = $i % 2 === 0 ? 'B' : 'A';
            $equipoCompleto = $equipoIncompleto === 'A' ? 'B' : 'A';

            $grupos = [
                // Un partido con un equipo reservado entero y otro con el mismo equipo armado de a grupos
                [$equipoCompleto, $this->repartir($turno->cupoEquipo($equipoCompleto), $i % 2 === 0)],
                [$equipoIncompleto, $this->repartir($turno->cupoEquipo($equipoIncompleto) - 1, false)],
            ];

            foreach ($grupos as [$equipo, $tandas]) {
                foreach ($tandas as [$tipo, $cantidad]) {
                    $reserva = Reserva::create([
                        'turno_id' => $turno->id,
                        'usuario_id' => $jugadores[$siguiente++ % $jugadores->count()],
                        'tipo' => $tipo,
                        'equipo' => $equipo,
                        'cantidad_jugadores' => $cantidad,
                        'estado' => 'confirmada',
                        'estado_pago' => 'pendiente',
                    ]);

                    // Igual que JugadorAnotadoSeeder con los partidos de ejemplo
                    if ($tipo === 'individual') {
                        JugadorAnotado::firstOrCreate(
                            ['reserva_id' => $reserva->id, 'usuario_id' => $reserva->usuario_id],
                            ['fecha_inscripcion' => $reserva->created_at],
                        );
                    }
                }
            }
            // El turno sigue "disponible": le falta un jugador
        }

        $this->command?->info("Se armaron {$turnos->count()} partidos a un lugar de completarse.");
    }

    // Partidos que todavía no empezaron y a los que les falta exactamente un jugador
    private function partidosCasiLlenos(): Collection
    {
        return Turno::where('estado', 'disponible')
            ->whereDate('fecha', '>=', now()->toDateString())
            ->whereHas('reservas', fn ($q) => $q->delPartido())
            ->get()
            ->filter(fn (Turno $t) => ! $t->yaPaso()
                && $t->libresEquipo('A') + $t->libresEquipo('B') === 1);
    }

    // Turnos sin ninguna reserva que empiezan dentro de al menos una hora; primero uno por cancha
    private function turnosLibres(int $cantidad): Collection
    {
        $desde = now()->addHour();

        $candidatos = Turno::where('estado', 'disponible')
            ->whereBetween('fecha', [now()->toDateString(), now()->addDay()->toDateString()])
            ->where('cupo_maximo', '>=', 2)
            ->whereDoesntHave('reservas')
            ->whereHas('cancha', fn ($q) => $q->where('estado', 'activa'))
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get()
            ->filter(fn (Turno $t) => $t->inicio()->gte($desde))
            ->values();

        // Para que el lobby se vea variado: primero cancha y horario distintos, después solo cancha distinta, y si no alcanza, cualquiera
        $elegidos = collect();
        $criterios = [
            fn (Turno $t) => ! $elegidos->contains('cancha_id', $t->cancha_id)
                && ! $elegidos->contains(fn (Turno $e) => $e->fecha == $t->fecha && $e->hora_inicio == $t->hora_inicio),
            fn (Turno $t) => ! $elegidos->contains('cancha_id', $t->cancha_id),
            fn (Turno $t) => true,
        ];

        foreach ($criterios as $sirve) {
            foreach ($candidatos as $turno) {
                if ($elegidos->count() >= $cantidad) {
                    break 2;
                }
                if (! $elegidos->contains('id', $turno->id) && $sirve($turno)) {
                    $elegidos->push($turno);
                }
            }
        }

        return $elegidos;
    }

    // Jugadores de prueba: primero los del historial de demo, así jugador1..8 quedan libres para sumarse en vivo
    private function jugadores(): Collection
    {
        $prueba = User::where('rol', 'jugador')->where('email', 'like', '%@f5gol.com')->orderBy('id');
        $demo = (clone $prueba)->where('email', 'like', 'demo%')->pluck('id');

        return $demo->isNotEmpty() ? $demo : $prueba->pluck('id');
    }

    /**
     * Divide a los jugadores de un equipo en reservas: un equipo entero en una sola reserva,
     * o grupos de hasta 3 (uno que se anota con amigos).
     *
     * @return array<int, array{0: string, 1: int}>
     */
    private function repartir(int $jugadores, bool $equipoEntero): array
    {
        if ($jugadores <= 0) {
            return [];
        }

        if ($equipoEntero) {
            return [['equipo', $jugadores]];
        }

        $tandas = [];
        while ($jugadores > 0) {
            $cantidad = min(3, $jugadores);
            $tandas[] = ['individual', $cantidad];
            $jugadores -= $cantidad;
        }

        return $tandas;
    }
}
