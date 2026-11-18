<?php

namespace App\Console\Commands;

use App\Jobs\AvisarPartidoIncompleto;
use App\Models\Turno;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Busca los partidos de matchmaking que llegaron a su hora sin completar el cupo
 * y avisa a los anotados. Corre solo cada 5 minutos (routes/console.php)
 * con `php artisan schedule:work`; también se puede correr a mano.
 */
#[Signature('partidos:avisar-incompletos')]
#[Description('Avisa por mail a los jugadores de los partidos de matchmaking que empezaron sin completarse')]
class AvisarPartidosIncompletos extends Command
{
    // No se avisan partidos que empezaron hace más de esto (por si el programador estuvo apagado)
    private const HORAS_HACIA_ATRAS = 6;

    public function handle(): int
    {
        $ahora = now();
        $desde = $ahora->copy()->subHours(self::HORAS_HACIA_ATRAS);

        $candidatos = Turno::query()
            ->whereNull('aviso_incompleto_at')
            ->where('estado', 'disponible')
            ->whereBetween('fecha', [$desde->toDateString(), $ahora->toDateString()])
            ->whereHas('reservas', fn ($q) => $q->where('tipo', 'individual')->where('estado', '!=', 'cancelada'))
            ->get();

        $avisados = 0;
        foreach ($candidatos as $turno) {
            $inicio = $turno->inicio();

            if ($inicio->greaterThan($ahora) || $inicio->lessThan($desde) || $turno->cupoActual() >= $turno->cupo_maximo) {
                continue;
            }

            // Primero se marca, así un aviso que tarda no se manda dos veces si el comando se superpone
            $turno->update(['aviso_incompleto_at' => $ahora]);
            AvisarPartidoIncompleto::dispatchSync($turno);
            $avisados++;
        }

        $this->info($avisados === 1 ? 'Se avisó 1 partido incompleto.' : "Se avisaron {$avisados} partidos incompletos.");

        return self::SUCCESS;
    }
}
