<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'club_id',
    'nombre',
    'tipo',
    'capacidad_jugadores',
    'descripcion',
    'tarifa',
    'estado',
    'fotos',
])]
class Cancha extends Model
{
    use HasFactory;

    // URLs públicas de las fotos, en el orden en que se subieron
    protected $casts = [
        'fotos' => 'array',
    ];

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
