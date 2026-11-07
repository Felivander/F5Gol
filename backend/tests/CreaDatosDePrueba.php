<?php

namespace Tests;

use App\Models\Cancha;
use App\Models\Club;
use App\Models\Reserva;
use App\Models\Turno;
use App\Models\User;

/**
 * Atajos para armar los datos que usan los tests de la API.
 */
trait CreaDatosDePrueba
{
    protected function crearAdministrador(): User
    {
        return User::factory()->create(['rol' => 'administrador']);
    }

    protected function crearJugador(): User
    {
        return User::factory()->create(['rol' => 'jugador']);
    }

    protected function crearClub(?User $administrador = null): Club
    {
        return Club::create([
            'administrador_id' => ($administrador ?? $this->crearAdministrador())->id,
            'nombre' => 'Club de prueba',
        ]);
    }

    protected function crearCancha(?Club $club = null): Cancha
    {
        return Cancha::create([
            'club_id' => ($club ?? $this->crearClub())->id,
            'nombre' => 'Cancha 1',
            'tarifa' => 20000,
        ]);
    }

    protected function crearTurno(?Cancha $cancha = null, array $datos = []): Turno
    {
        return Turno::create(array_merge([
            'cancha_id' => ($cancha ?? $this->crearCancha())->id,
            'fecha' => now()->addDays(2)->toDateString(),
            'hora_inicio' => '20:00',
            'hora_fin' => '21:00',
        ], $datos));
    }

    protected function crearReserva(Turno $turno, array $datos = []): Reserva
    {
        return Reserva::create(array_merge([
            'turno_id' => $turno->id,
            'usuario_id' => $this->crearJugador()->id,
            'tipo' => 'completa',
            'cantidad_jugadores' => 1,
            'estado' => 'confirmada',
        ], $datos));
    }
}
