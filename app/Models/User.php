<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'rol', 'telefono'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Un administrador puede tener uno o más clubes
    public function clubs()
    {
        return $this->hasMany(Club::class, 'administrador_id');
    }

    // Un jugador puede tener muchas reservas
    public function reservas()
    {
        return $this->hasMany(Reserva::class, 'usuario_id');
    }

    // Un jugador puede estar anotado en muchos turnos de matchmaking
    public function jugadorAnotados()
    {
        return $this->hasMany(JugadorAnotado::class, 'usuario_id');
    }
}
