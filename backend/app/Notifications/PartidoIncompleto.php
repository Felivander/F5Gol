<?php

namespace App\Notifications;

use App\Models\Turno;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Respaldo por mail de Laravel cuando n8n no está: el partido llegó a la hora sin completar el cupo.
 */
class PartidoIncompleto extends Notification
{
    public function __construct(public Turno $turno)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $cancha = $this->turno->cancha;
        $anotados = $this->turno->cupoActual();

        return (new MailMessage)
            ->subject('Tu partido en '.$cancha?->nombre.' no llegó a completarse')
            ->greeting('Hola '.$notifiable->name.',')
            ->line('Llegó la hora del partido '.$this->turno->textoHorario()." y se anotaron {$anotados} de {$this->turno->cupo_maximo} jugadores "
                .'(Equipo A: '.$this->turno->anotadosEquipo('A').' de '.$this->turno->cupoEquipo('A')
                .', Equipo B: '.$this->turno->anotadosEquipo('B').' de '.$this->turno->cupoEquipo('B').').')
            ->line('Hablá con el club para ver si igual se puede jugar, o buscá otro partido abierto en F5GOL.');
    }
}
