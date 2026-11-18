<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'turno_id',
    'usuario_id',
    'tipo',
    'cantidad_jugadores',
    'equipo',
    'estado',
    'estado_pago',
])]
class Reserva extends Model
{
    use HasFactory;

    // Un jugador puede cancelar hasta esta cantidad de horas antes del partido
    public const HORAS_MINIMAS_PARA_CANCELAR = 2;

    // Reservas que forman parte de un partido de matchmaking:
    // un lugar suelto ("individual") o un equipo entero ("equipo")
    public const TIPOS_PARTIDO = ['individual', 'equipo'];

    protected static function booted(): void
    {
        // Una reserva de partido sin equipo (seeders, carga directa) va al que tenga más lugares libres
        static::creating(function (Reserva $reserva) {
            if (in_array($reserva->tipo, self::TIPOS_PARTIDO, true) && ! $reserva->equipo) {
                $reserva->equipo = Turno::find($reserva->turno_id)?->equipoConMasLugares() ?? 'A';
            }
        });
    }

    // Reservas activas del partido (sin las canceladas)
    public function scopeDelPartido($query)
    {
        return $query->whereIn('tipo', self::TIPOS_PARTIDO)->where('estado', '!=', 'cancelada');
    }

    // Una reserva pertenece a un turno
    public function turno()
    {
        return $this->belongsTo(Turno::class);
    }

    // Una reserva pertenece a un usuario (jugador)
    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    // Una reserva individual (matchmaking) tiene varios jugadores anotados
    public function jugadorAnotados()
    {
        return $this->hasMany(JugadorAnotado::class);
    }
}
