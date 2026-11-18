<?php

namespace App\Jobs;

use App\Models\Turno;
use App\Models\User;
use App\Notifications\PartidoCompleto;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Avisa a todos los jugadores anotados que su partido de matchmaking se completó.
 *
 * Si n8n está configurado (N8N_WEBHOOK_PARTIDO_COMPLETO), n8n manda un mail a cada jugador.
 * Si no está configurado o no responde bien, se usa el mail de Laravel (PartidoCompleto)
 * como respaldo: el aviso nunca se pierde. Corre después de responder la reserva.
 */
class AvisarPartidoCompleto
{
    use Dispatchable;

    public function __construct(public Turno $turno) {}

    public function handle(): void
    {
        $turno = $this->turno->loadMissing('cancha.club');

        $reservas = $turno->reservas()
            ->delPartido()
            ->with('usuario:id,name,email')
            ->get();
        $jugadores = $reservas->pluck('usuario')->filter()->unique('id')->values();

        if ($jugadores->isEmpty()) {
            return;
        }

        if ($this->avisarPorN8n($turno, $reservas)) {
            return;
        }

        // Respaldo: mail de Laravel (con MAIL_MAILER=log queda en storage/logs/laravel.log)
        Notification::send($jugadores, $this->notificacion($turno));
    }

    // Lo que cambia entre el aviso de partido completo y el de partido incompleto (ver AvisarPartidoIncompleto)
    protected function evento(): string
    {
        return 'partido.completo';
    }

    protected function claveWebhook(): string
    {
        return 'webhook_partido_completo';
    }

    protected function notificacion(Turno $turno): \Illuminate\Notifications\Notification
    {
        return new PartidoCompleto($turno);
    }

    private function avisarPorN8n(Turno $turno, $reservas): bool
    {
        $url = config('services.n8n.'.$this->claveWebhook());

        if (! $url) {
            return false;
        }

        $frontend = rtrim(config('services.frontend.url'), '/');
        $fecha = Carbon::parse(substr($turno->fecha, 0, 10))->locale('es');
        $cancha = $turno->cancha;
        $club = $cancha?->club;

        try {
            Http::timeout(10)
                ->withHeaders(['X-F5GOL-Secreto' => (string) config('services.n8n.secreto')])
                ->post($url, [
                    'evento' => $this->evento(),
                    'turno' => [
                        'id' => $turno->id,
                        'fecha' => $fecha->toDateString(),
                        'dia' => $fecha->isoFormat('dddd D/MM'),
                        'hora_inicio' => substr($turno->hora_inicio, 0, 5),
                        'hora_fin' => substr($turno->hora_fin, 0, 5),
                        'cupo_maximo' => $turno->cupo_maximo,
                        'anotados' => (int) $reservas->sum('cantidad_jugadores'),
                    ],
                    'cancha' => [
                        'nombre' => $cancha?->nombre,
                        'tipo' => $cancha?->tipo,
                    ],
                    'club' => [
                        'nombre' => $club?->nombre,
                        'direccion' => $club?->direccion,
                        'telefono' => $club?->telefono,
                    ],
                    // Un elemento por reserva: cada jugador sabe en qué equipo juega y con cuántos amigos se anotó
                    'jugadores' => $reservas
                        ->filter(fn ($r) => $r->usuario instanceof User)
                        ->map(fn ($r) => [
                            'nombre' => $r->usuario->name,
                            'email' => $r->usuario->email,
                            'cantidad_jugadores' => $r->cantidad_jugadores,
                            'equipo' => $r->equipo,
                            'tipo' => $r->tipo,
                        ])->values()->all(),
                    'links' => [
                        'cancha' => $frontend.'/canchas/'.$cancha?->id,
                        'mis_reservas' => $frontend.'/mis-reservas',
                        'partidos' => $frontend.'/partidos',
                    ],
                ])
                ->throw();

            return true;
        } catch (Throwable $e) {
            Log::warning("No se pudo avisar a n8n el evento {$this->evento()} del turno {$turno->id}; se usa el mail de Laravel: ".$e->getMessage());

            return false;
        }
    }
}
