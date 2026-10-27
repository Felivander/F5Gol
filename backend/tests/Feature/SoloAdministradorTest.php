<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\CreaDatosDePrueba;
use Tests\TestCase;

class SoloAdministradorTest extends TestCase
{
    use CreaDatosDePrueba, RefreshDatabase;

    public function test_un_jugador_no_puede_crear_canchas(): void
    {
        $club = $this->crearClub();
        Sanctum::actingAs($this->crearJugador());

        $this->postJson('/api/canchas', ['club_id' => $club->id, 'nombre' => 'Nueva', 'tarifa' => 1000])
            ->assertForbidden()
            ->assertJsonPath('message', 'Solo un administrador puede realizar esta acción.');

        $this->assertDatabaseMissing('canchas', ['nombre' => 'Nueva']);
    }

    public function test_un_jugador_no_puede_editar_ni_borrar_canchas(): void
    {
        $cancha = $this->crearCancha();
        Sanctum::actingAs($this->crearJugador());

        $this->putJson("/api/canchas/{$cancha->id}", ['tarifa' => 1])->assertForbidden();
        $this->deleteJson("/api/canchas/{$cancha->id}")->assertForbidden();

        $this->assertDatabaseHas('canchas', ['id' => $cancha->id, 'tarifa' => 20000]);
    }

    public function test_un_jugador_no_puede_gestionar_turnos_ni_clubes(): void
    {
        $turno = $this->crearTurno();
        Sanctum::actingAs($this->crearJugador());

        $this->putJson("/api/turnos/{$turno->id}", ['estado' => 'bloqueado'])->assertForbidden();
        $this->postJson('/api/clubs', ['nombre' => 'Otro club'])->assertForbidden();
    }

    public function test_un_administrador_puede_crear_canchas(): void
    {
        $admin = $this->crearAdministrador();
        $club = $this->crearClub($admin);
        Sanctum::actingAs($admin);

        $this->postJson('/api/canchas', ['club_id' => $club->id, 'nombre' => 'Nueva', 'tarifa' => 1000])
            ->assertCreated();
    }

    public function test_un_jugador_puede_seguir_reservando(): void
    {
        $turno = $this->crearTurno();
        $jugador = $this->crearJugador();
        Sanctum::actingAs($jugador);

        $this->postJson('/api/reservas', ['turno_id' => $turno->id, 'usuario_id' => $jugador->id])
            ->assertCreated();
    }

    public function test_sin_token_sigue_respondiendo_401(): void
    {
        $this->postJson('/api/canchas', [])->assertUnauthorized();
    }
}
