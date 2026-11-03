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

    public function test_no_deja_subir_jugadores_por_encima_del_cupo(): void
    {
        $turno = $this->crearTurno();
        $this->crearReserva($turno, ['tipo' => 'individual', 'cantidad_jugadores' => 3]);
        $reserva = $this->crearReserva($turno, ['tipo' => 'individual', 'cantidad_jugadores' => 1]);
        Sanctum::actingAs($reserva->usuario);

        $this->putJson("/api/reservas/{$reserva->id}", ['cantidad_jugadores' => 3])
            ->assertStatus(409)
            ->assertJsonPath('message', 'No hay cupo suficiente en este turno');

        $this->assertSame(1, $reserva->fresh()->cantidad_jugadores);
        $this->assertSame('disponible', $turno->fresh()->estado);
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

    public function test_al_cambiar_de_turno_libera_el_anterior_y_ocupa_el_nuevo(): void
    {
        $anterior = $this->crearTurno(null, ['estado' => 'ocupado']);
        $nuevo = $this->crearTurno($anterior->cancha, ['hora_inicio' => '21:00', 'hora_fin' => '22:00']);
        $reserva = $this->crearReserva($anterior);
        Sanctum::actingAs($reserva->usuario);

        $this->putJson("/api/reservas/{$reserva->id}", ['turno_id' => $nuevo->id])
            ->assertOk()
            ->assertJsonPath('reserva.turno_id', $nuevo->id);

        $this->assertSame('disponible', $anterior->fresh()->estado);
        $this->assertSame('ocupado', $nuevo->fresh()->estado);
    }

    public function test_no_deja_cambiar_a_un_turno_ocupado(): void
    {
        $anterior = $this->crearTurno(null, ['estado' => 'ocupado']);
        $nuevo = $this->crearTurno($anterior->cancha, ['hora_inicio' => '21:00', 'hora_fin' => '22:00', 'estado' => 'ocupado']);
        $reserva = $this->crearReserva($anterior);
        Sanctum::actingAs($reserva->usuario);

        $this->putJson("/api/reservas/{$reserva->id}", ['turno_id' => $nuevo->id])
            ->assertStatus(409)
            ->assertJsonPath('message', 'El turno nuevo no está disponible');

        $this->assertSame($anterior->id, $reserva->fresh()->turno_id);
        $this->assertSame('ocupado', $anterior->fresh()->estado);
    }

    public function test_no_deja_mover_una_reserva_completa_a_un_turno_con_anotados(): void
    {
        $anterior = $this->crearTurno(null, ['estado' => 'ocupado']);
        $nuevo = $this->crearTurno($anterior->cancha, ['hora_inicio' => '21:00', 'hora_fin' => '22:00']);
        $this->crearReserva($nuevo, ['tipo' => 'individual', 'cantidad_jugadores' => 2]);
        $reserva = $this->crearReserva($anterior);
        Sanctum::actingAs($reserva->usuario);

        $this->putJson("/api/reservas/{$reserva->id}", ['turno_id' => $nuevo->id])
            ->assertStatus(409)
            ->assertJsonPath('message', 'El turno nuevo ya tiene jugadores anotados');
    }

    public function test_no_deja_cambiar_a_un_turno_que_ya_paso(): void
    {
        $anterior = $this->crearTurno(null, ['estado' => 'ocupado']);
        $nuevo = $this->crearTurno($anterior->cancha, ['fecha' => now()->subDay()->toDateString()]);
        $reserva = $this->crearReserva($anterior);
        Sanctum::actingAs($reserva->usuario);

        $this->putJson("/api/reservas/{$reserva->id}", ['turno_id' => $nuevo->id])->assertStatus(409);

        $this->assertSame($anterior->id, $reserva->fresh()->turno_id);
    }

    public function test_el_jugador_no_puede_cambiar_de_turno_fuera_de_plazo(): void
    {
        $inicio = now()->addHour();
        $anterior = $this->crearTurno(null, [
            'fecha' => $inicio->toDateString(),
            'hora_inicio' => $inicio->format('H:i'),
            'hora_fin' => $inicio->copy()->addHour()->format('H:i'),
            'estado' => 'ocupado',
        ]);
        $nuevo = $this->crearTurno($anterior->cancha, ['fecha' => now()->addDays(3)->toDateString()]);
        $reserva = $this->crearReserva($anterior);
        Sanctum::actingAs($reserva->usuario);

        $this->putJson("/api/reservas/{$reserva->id}", ['turno_id' => $nuevo->id])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Las reservas se pueden cambiar de turno hasta 2 horas antes del partido.');

        $this->assertSame($anterior->id, $reserva->fresh()->turno_id);
    }

    public function test_el_jugador_no_puede_crear_una_reserva_ya_pagada(): void
    {
        $jugador = $this->crearJugador();
        $turno = $this->crearTurno();
        Sanctum::actingAs($jugador);

        $this->postJson('/api/reservas', [
            'turno_id' => $turno->id,
            'usuario_id' => $jugador->id,
            'estado_pago' => 'pagado',
        ])->assertCreated();

        $this->assertNotSame('pagado', $turno->reservas()->first()->estado_pago);
    }

    public function test_el_jugador_no_puede_cambiar_el_estado_de_pago(): void
    {
        $reserva = $this->crearReserva($this->crearTurno());
        Sanctum::actingAs($reserva->usuario);

        $this->putJson("/api/reservas/{$reserva->id}", ['estado_pago' => 'pagado'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Solo el administrador del club puede cambiar el estado de pago');

        $this->assertNotSame('pagado', $reserva->fresh()->estado_pago);
    }

    public function test_el_administrador_del_club_puede_cambiar_el_estado_de_pago(): void
    {
        $admin = $this->crearAdministrador();
        $reserva = $this->crearReserva($this->crearTurno($this->crearCancha($this->crearClub($admin))));
        Sanctum::actingAs($admin);

        $this->putJson("/api/reservas/{$reserva->id}", ['estado_pago' => 'sena'])
            ->assertOk()
            ->assertJsonPath('reserva.estado_pago', 'sena');

        // El jugador ve el estado nuevo en su listado
        $this->getJson("/api/reservas?usuario_id={$reserva->usuario_id}")
            ->assertJsonPath('data.0.estado_pago', 'sena');
    }

    public function test_otro_administrador_no_puede_editar_la_reserva(): void
    {
        $reserva = $this->crearReserva($this->crearTurno());
        Sanctum::actingAs($this->crearAdministrador());

        $this->putJson("/api/reservas/{$reserva->id}", ['estado_pago' => 'pagado'])->assertForbidden();
    }

    public function test_un_jugador_no_puede_editar_la_reserva_de_otro(): void
    {
        $reserva = $this->crearReserva($this->crearTurno(), ['tipo' => 'individual']);
        Sanctum::actingAs($this->crearJugador());

        $this->putJson("/api/reservas/{$reserva->id}", ['cantidad_jugadores' => 2])
            ->assertForbidden()
            ->assertJsonPath('message', 'Solo podés editar tus propias reservas o las de tus clubes.');
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
