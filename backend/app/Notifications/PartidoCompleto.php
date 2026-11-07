<?php

namespace App\Notifications;

use App\Models\Turno;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Se manda a cada jugador anotado por matchmaking cuando el turno llega al cupo máximo.
 * En desarrollo MAIL_MAILER=log deja el mail en storage/logs/laravel.log.
 */
class PartidoCompleto extends Notification
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
        $fecha = substr($this->turno->fecha, 0, 10);
        $horario = substr($this->turno->hora_inicio, 0, 5).' a '.substr($this->turno->hora_fin, 0, 5);

        return (new MailMessage)
            ->subject('¡Se completó tu partido en '.$cancha?->nombre.'!')
            ->greeting('¡Hola '.$notifiable->name.'!')
            ->line("El partido del {$fecha} de {$horario} en {$cancha?->nombre} ya tiene los {$this->turno->cupo_maximo} jugadores.")
            ->line('El turno quedó confirmado: nos vemos en la cancha.');
    }
}
