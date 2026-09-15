<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['administrador_id', 'nombre', 'direccion', 'telefono'])]
class Club extends Model
{
    use HasFactory;

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
