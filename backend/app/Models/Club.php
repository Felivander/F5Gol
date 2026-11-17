<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['administrador_id', 'nombre', 'direccion', 'telefono', 'descripcion', 'fotos'])]
class Club extends Model
{
    use HasFactory;

    // URLs públicas de las fotos, en el orden en que se subieron
    protected $casts = [
        'fotos' => 'array',
    ];

    // Un club pertenece a un administrador (dueño)
    public function administrador()
    {
        return $this->belongsTo(User::class, 'administrador_id');
    }

    // Un club tiene muchas canchas
    public function canchas()
    {
        return $this->hasMany(Cancha::class);
    }
}
