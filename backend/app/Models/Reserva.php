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
    'estado',
    'estado_pago',
])]
class Reserva extends Model
{
    use HasFactory;

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
