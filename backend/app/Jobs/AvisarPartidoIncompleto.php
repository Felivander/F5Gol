<?php

namespace App\Jobs;

use App\Models\Turno;
use App\Notifications\PartidoIncompleto;
use Illuminate\Notifications\Notification;

/**
 * Avisa a los jugadores anotados que su partido de matchmaking llegó a la hora sin completarse.
 * Mismo camino que el aviso de partido completo: n8n (N8N_WEBHOOK_PARTIDO_INCOMPLETO) y,
 * si no está o falla, el mail de Laravel (PartidoIncompleto).
 */
class AvisarPartidoIncompleto extends AvisarPartidoCompleto
{
    protected function evento(): string
    {
        return 'partido.incompleto';
    }

    protected function claveWebhook(): string
    {
        return 'webhook_partido_incompleto';
    }

    protected function notificacion(Turno $turno): Notification
    {
        return new PartidoIncompleto($turno);
    }
}
