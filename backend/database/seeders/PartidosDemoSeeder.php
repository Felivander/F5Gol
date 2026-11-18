<?php

namespace Database\Seeders;

use App\Models\JugadorAnotado;
use App\Models\Reserva;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class PartidosDemoSeeder extends Seeder
{
    // Los partidos que se arman, en este orden (el vacío es un turno libre que se deja sin nadie)
    public const ESCENARIOS = ['falta_uno', 'mitad', 'completo'];

    /**
     * Run the database seeds.
     *
     * Para la demo deja un partido de matchmaking en cada estado, en canchas y horarios distintos:
     * - falta_uno: le falta un jugador. Al sumarse alguien se completa, pasa a "ocupado" y se dispara
     *   el aviso de partido completo (el flujo de n8n o, si n8n no responde, el mail de Laravel).
     * - mitad: el Equipo A completo y el B vacío (el B se puede reservar entero).
     * - completo: los dos equipos llenos; el turno queda "ocupado" y no aparece en el lobby.
     * Además informa un turno vacío para arrancar un partido desde cero.
     *
     * Usa turnos que empiezan dentro de al menos una hora, así que conviene correrlo el mismo día de la
     * presentación. Se puede correr más de una vez: solo arma los escenarios que no estén.
     */
    public function run(): void
    {
        $faltan = collect(self::ESCENARIOS)->reject(fn ($escenario) => $this->hay($escenario))->values();

        if ($faltan->isNotEmpty()) {
            $turnos = $this->turnosLibres($faltan->count() + 1);
            $jugadores = $this->jugadores();

            if ($turnos->count() <= $faltan->count() || $jugadores->isEmpty()) {
                $this->command?->warn('No hay turnos libres de acá a mañana para armar los partidos de la demo. Corré antes TurnoSeeder.');

                return;
            }

            $siguiente = 0;
            foreach ($faltan as $i => $escenario) {
                $this->armar($escenario, $turnos[$i], $jugadores, $siguiente);
            }
        }

        $this->informar();
    }

    // ¿Ya hay un partido de este escenario que todavía no empezó?
    private function hay(string $escenario): bool
    {
        return $this->partidosFuturos()->contains(fn (Turno $t) => $this->escenarioDe($t) === $escenario);
    }

    private function partidosFuturos(): Collection
    {
        return Turno::whereDate('fecha', '>=', now()->toDateString())
            ->whereHas('reservas', fn ($q) => $q->delPartido())
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get()
            ->reject(fn (Turno $t) => $t->yaPaso());
    }

    // En qué estado está un partido: falta_uno, mitad, completo u otro
    private function escenarioDe(Turno $t): ?string
    {
        $libresA = $t->libresEquipo('A');
        $libresB = $t->libresEquipo('B');

        return match (true) {
            $t->estado === 'ocupado' && $libresA + $libresB === 0 => 'completo',
            $t->estado === 'disponible' && $libresA + $libresB === 1 => 'falta_uno',
            $t->estado === 'disponible' && $libresA === 0 && $t->anotadosEquipo('B') === 0 => 'mitad',
            default => null,
        };
    }

    private function armar(string $escenario, Turno $turno, Collection $jugadores, int &$siguiente): void
    {
        $cupoA = $turno->cupoEquipo('A');
        $cupoB = $turno->cupoEquipo('B');

        // [equipo, reservas]: un equipo reservado entero en una sola reserva, o armado de a grupos (uno con amigos)
        $grupos = match ($escenario) {
            'falta_uno' => [['A', [['equipo', $cupoA]]], ['B', $this->deAGrupos($cupoB - 1)]],
            'mitad' => [['A', $this->deAGrupos($cupoA)]],
            'completo' => [['A', $this->deAGrupos($cupoA)], ['B', [['equipo', $cupoB]]]],
        };

        foreach ($grupos as [$equipo, $reservas]) {
            foreach ($reservas as [$tipo, $cantidad]) {
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

        // Con los dos equipos llenos, el turno queda ocupado como cuando se completa desde la app
        if ($escenario === 'completo') {
            $turno->update(['estado' => 'ocupado']);
        }
    }

    // Dice en la consola qué partido quedó en cada estado, para saber dónde ir en la demo
    private function informar(): void
    {
        $nombres = ['falta_uno' => 'Le falta uno', 'mitad' => 'Mitad lleno', 'completo' => 'Completo'];
        $partidos = $this->partidosFuturos();

        foreach (self::ESCENARIOS as $escenario) {
            $turno = $partidos->first(fn (Turno $t) => $this->escenarioDe($t) === $escenario);
            if ($turno) {
                $this->command?->info(str_pad($nombres[$escenario].':', 14).$this->describir($turno));
            }
        }

        $vacio = $this->turnosLibres(1)->first();
        if ($vacio) {
            $this->command?->info(str_pad('Vacío:', 14).$this->describir($vacio));
        }
    }

    private function describir(Turno $t): string
    {
        $t->loadMissing('cancha.club:id,nombre');

        return $t->cancha->club?->nombre.' · '.$t->cancha->nombre.', '.substr($t->fecha, 0, 10).' a las '.substr($t->hora_inicio, 0, 5)
            ." ({$t->cupoActual()}/{$t->cupo_maximo}, turno {$t->id})";
    }

    // Turnos sin ninguna reserva que empiezan dentro de al menos una hora, en canchas y horarios distintos si se puede
    private function turnosLibres(int $cantidad): Collection
    {
        $desde = now()->addHour();

        $candidatos = Turno::where('estado', 'disponible')
            ->whereBetween('fecha', [now()->toDateString(), now()->addDay()->toDateString()])
            ->where('cupo_maximo', '>=', 4)
            ->whereDoesntHave('reservas')
            ->whereHas('cancha', fn ($q) => $q->where('estado', 'activa'))
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get()
            ->filter(fn (Turno $t) => $t->inicio()->gte($desde))
            ->values();

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

        return $elegidos->values();
    }

    // Jugadores de prueba: primero los del historial de demo, así jugador1..8 quedan libres para sumarse en vivo
    private function jugadores(): Collection
    {
        $prueba = User::where('rol', 'jugador')->where('email', 'like', '%@f5gol.com')->orderBy('id');
        $demo = (clone $prueba)->where('email', 'like', 'demo%')->pluck('id');

        return $demo->isNotEmpty() ? $demo : $prueba->pluck('id');
    }

    /**
     * Reparte jugadores en reservas de hasta 3 (uno que se anota con amigos).
     *
     * @return array<int, array{0: string, 1: int}>
     */
    private function deAGrupos(int $jugadores): array
    {
        $reservas = [];
        while ($jugadores > 0) {
            $cantidad = min(3, $jugadores);
            $reservas[] = ['individual', $cantidad];
            $jugadores -= $cantidad;
        }

        return $reservas;
    }
}
