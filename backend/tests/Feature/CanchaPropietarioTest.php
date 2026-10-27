<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\CreaDatosDePrueba;
use Tests\TestCase;

class CanchaPropietarioTest extends TestCase
{
    use CreaDatosDePrueba, RefreshDatabase;

    public function test_el_dueno_puede_editar_y_borrar_su_cancha(): void
    {
        $admin = $this->crearAdministrador();
        $cancha = $this->crearCancha($this->crearClub($admin));
        Sanctum::actingAs($admin);

        $this->putJson("/api/canchas/{$cancha->id}", ['tarifa' => 30000])->assertOk();
        $this->assertDatabaseHas('canchas', ['id' => $cancha->id, 'tarifa' => 30000]);

        $this->deleteJson("/api/canchas/{$cancha->id}")->assertOk();
        $this->assertDatabaseMissing('canchas', ['id' => $cancha->id]);
    }

    public function test_otro_administrador_no_puede_editar_la_cancha(): void
    {
        $cancha = $this->crearCancha();
        Sanctum::actingAs($this->crearAdministrador());

        $this->putJson("/api/canchas/{$cancha->id}", ['tarifa' => 1])
            ->assertForbidden()
            ->assertJsonPath('message', 'Solo podés gestionar las canchas de tus propios clubes.');

        $this->assertDatabaseHas('canchas', ['id' => $cancha->id, 'tarifa' => 20000]);
    }

    public function test_otro_administrador_no_puede_borrar_la_cancha(): void
    {
        $cancha = $this->crearCancha();
        Sanctum::actingAs($this->crearAdministrador());

        $this->deleteJson("/api/canchas/{$cancha->id}")->assertForbidden();
        $this->assertDatabaseHas('canchas', ['id' => $cancha->id]);
    }

    public function test_no_puede_crear_canchas_en_un_club_ajeno(): void
    {
        $clubAjeno = $this->crearClub();
        Sanctum::actingAs($this->crearAdministrador());

        $this->postJson('/api/canchas', ['club_id' => $clubAjeno->id, 'nombre' => 'Intrusa', 'tarifa' => 1000])
            ->assertForbidden();

        $this->assertDatabaseMissing('canchas', ['nombre' => 'Intrusa']);
    }

    public function test_no_puede_mover_su_cancha_a_un_club_ajeno(): void
    {
        $admin = $this->crearAdministrador();
        $cancha = $this->crearCancha($this->crearClub($admin));
        $clubAjeno = $this->crearClub();
        Sanctum::actingAs($admin);

        $this->putJson("/api/canchas/{$cancha->id}", ['club_id' => $clubAjeno->id])
            ->assertForbidden();

        $this->assertDatabaseHas('canchas', ['id' => $cancha->id, 'club_id' => $cancha->club_id]);
    }
}
