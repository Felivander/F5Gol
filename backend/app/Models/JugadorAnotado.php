<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['reserva_id', 'usuario_id', 'fecha_inscripcion'])]
class JugadorAnotado extends Model
{
    use HasFactory;

    // Un jugador anotado pertenece a una reserva de matchmaking
    public function reserva()
    {
        return $this->belongsTo(Reserva::class);
    }

    // Un jugador anotado pertenece a un usuario
    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
