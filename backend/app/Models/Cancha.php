<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'club_id',
    'nombre',
    'tipo',
    'capacidad_jugadores',
    'descripcion',
    'tarifa',
    'estado',
])]
class Cancha extends Model
{
    use HasFactory;
    use SoftDeletes;

    // Una cancha pertenece a un club
    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    // Una cancha tiene muchos turnos (grilla de horarios)
    public function turnos()
    {
        return $this->hasMany(Turno::class);
    }
}
