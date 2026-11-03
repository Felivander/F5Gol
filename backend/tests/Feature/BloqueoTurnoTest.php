<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\CreaDatosDePrueba;
use Tests\TestCase;

class BloqueoTurnoTest extends TestCase
{
    use CreaDatosDePrueba, RefreshDatabase;

    public function test_el_dueno_bloquea_un_turno_libre_y_no_se_puede_reservar(): void
    {
        $admin = $this->crearAdministrador();
        $turno = $this->crearTurno($this->crearCancha($this->crearClub($admin)));
        Sanctum::actingAs($admin);

        $this->patchJson("/api/turnos/{$turno->id}/bloqueo", ['bloqueado' => true])
            ->assertOk()
            ->assertJsonPath('turno.estado', 'bloqueado');

        $jugador = $this->crearJugador();
        Sanctum::actingAs($jugador);
        $this->postJson('/api/reservas', ['turno_id' => $turno->id, 'usuario_id' => $jugador->id])->assertStatus(409);
        $this->postJson('/api/reservas', ['turno_id' => $turno->id, 'usuario_id' => $jugador->id, 'tipo' => 'individual'])->assertStatus(409);
    }

    public function test_al_desbloquear_vuelve_a_estar_disponible(): void
    {
        $admin = $this->crearAdministrador();
        $turno = $this->crearTurno($this->crearCancha($this->crearClub($admin)), ['estado' => 'bloqueado']);
        Sanctum::actingAs($admin);

        $this->patchJson("/api/turnos/{$turno->id}/bloqueo", ['bloqueado' => false])
            ->assertOk()
            ->assertJsonPath('turno.estado', 'disponible');
    }

    public function test_no_deja_bloquear_un_turno_con_reservas_activas(): void
    {
        $admin = $this->crearAdministrador();
        $turno = $this->crearTurno($this->crearCancha($this->crearClub($admin)), ['estado' => 'ocupado']);
        $this->crearReserva($turno);
        Sanctum::actingAs($admin);

        $this->patchJson("/api/turnos/{$turno->id}/bloqueo", ['bloqueado' => true])
            ->assertStatus(409)
            ->assertJsonPath('message', 'El turno tiene reservas activas: cancelalas antes de bloquearlo.');

        $this->assertSame('ocupado', $turno->fresh()->estado);
    }

    public function test_otro_administrador_no_puede_bloquear(): void
    {
        $turno = $this->crearTurno();
        Sanctum::actingAs($this->crearAdministrador());

        $this->patchJson("/api/turnos/{$turno->id}/bloqueo", ['bloqueado' => true])->assertForbidden();

        $this->assertSame('disponible', $turno->fresh()->estado);
    }

    public function test_un_jugador_no_puede_bloquear(): void
    {
        $turno = $this->crearTurno();
        Sanctum::actingAs($this->crearJugador());

        $this->patchJson("/api/turnos/{$turno->id}/bloqueo", ['bloqueado' => true])->assertForbidden();
    }
}
