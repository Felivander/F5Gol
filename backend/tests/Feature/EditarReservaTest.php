<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\CreaDatosDePrueba;
use Tests\TestCase;

class EditarReservaTest extends TestCase
{
    use CreaDatosDePrueba, RefreshDatabase;

    public function test_al_sumar_jugadores_y_llenar_el_cupo_el_turno_queda_ocupado(): void
    {
        $turno = $this->crearTurno();
        $this->crearReserva($turno, ['tipo' => 'individual', 'cantidad_jugadores' => 3]);
        $reserva = $this->crearReserva($turno, ['tipo' => 'individual', 'cantidad_jugadores' => 1]);
        Sanctum::actingAs($reserva->usuario);

        $this->putJson("/api/reservas/{$reserva->id}", ['cantidad_jugadores' => 2])->assertOk();

        $this->assertSame('ocupado', $turno->fresh()->estado);
    }

    public function test_al_bajar_jugadores_el_turno_se_libera(): void
    {
        $turno = $this->crearTurno(null, ['estado' => 'ocupado']);
        $this->crearReserva($turno, ['tipo' => 'individual', 'cantidad_jugadores' => 3]);
        $reserva = $this->crearReserva($turno, ['tipo' => 'individual', 'cantidad_jugadores' => 2]);
        Sanctum::actingAs($reserva->usuario);

        $this->putJson("/api/reservas/{$reserva->id}", ['cantidad_jugadores' => 1])->assertOk();

        $this->assertSame('disponible', $turno->fresh()->estado);
    }

    public function test_editar_no_desbloquea_un_turno_bloqueado(): void
    {
        $turno = $this->crearTurno(null, ['estado' => 'bloqueado']);
        $reserva = $this->crearReserva($turno, ['tipo' => 'individual', 'cantidad_jugadores' => 1]);
        Sanctum::actingAs($reserva->usuario);

        $this->putJson("/api/reservas/{$reserva->id}", ['cantidad_jugadores' => 2])->assertOk();

        $this->assertSame('bloqueado', $turno->fresh()->estado);
    }
}
