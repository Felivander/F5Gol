<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'cancha_id',
    'fecha',
    'hora_inicio',
    'hora_fin',
    'estado',
    'cupo_maximo',
])]
class Turno extends Model
{
    use HasFactory;

    // Un turno pertenece a una cancha
    public function cancha()
    {
        return $this->belongsTo(Cancha::class);
    }

    // Un turno puede tener varias reservas (una completa, o varias individuales de matchmaking)
    public function reservas()
    {
        return $this->hasMany(Reserva::class);
    }

    // Cantidad de jugadores anotados actualmente vía matchmaking en este turno
    public function cupoActual(): int
    {
        return $this->reservas()
            ->where('tipo', 'individual')
            ->where('estado', '!=', 'cancelada')
            ->sum('cantidad_jugadores');
    }
}
