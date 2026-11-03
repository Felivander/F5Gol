<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\CreaDatosDePrueba;
use Tests\TestCase;

class CanchasInactivasTest extends TestCase
{
    use CreaDatosDePrueba, RefreshDatabase;

    public function test_el_listado_publico_no_muestra_canchas_inactivas(): void
    {
        $activa = $this->crearCancha();
        $inactiva = $this->crearCancha($activa->club);
        $inactiva->update(['estado' => 'inactiva']);

        $this->getJson('/api/canchas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $activa->id);
    }

    public function test_un_jugador_no_puede_pedir_las_inactivas(): void
    {
        $cancha = $this->crearCancha();
        $cancha->update(['estado' => 'inactiva']);
        Sanctum::actingAs($this->crearJugador());

        $this->getJson('/api/canchas?incluir_inactivas=1')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_el_administrador_puede_pedir_las_inactivas(): void
    {
        $cancha = $this->crearCancha();
        $cancha->update(['estado' => 'inactiva']);
        Sanctum::actingAs($this->crearAdministrador());

        $this->getJson('/api/canchas?incluir_inactivas=1')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_el_detalle_del_club_oculta_las_inactivas_salvo_al_dueno(): void
    {
        $admin = $this->crearAdministrador();
        $club = $this->crearClub($admin);
        $this->crearCancha($club);
        $this->crearCancha($club)->update(['estado' => 'inactiva']);

        $this->getJson("/api/clubs/{$club->id}")->assertOk()->assertJsonCount(1, 'canchas');

        Sanctum::actingAs($admin);
        $this->getJson("/api/clubs/{$club->id}")->assertOk()->assertJsonCount(2, 'canchas');
    }
}
