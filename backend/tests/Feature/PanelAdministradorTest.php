<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\CreaDatosDePrueba;
use Tests\TestCase;

class PanelAdministradorTest extends TestCase
{
    use CreaDatosDePrueba, RefreshDatabase;

    public function test_mis_clubes_devuelve_solo_las_reservas_de_sus_canchas(): void
    {
        $admin = $this->crearAdministrador();
        $propia = $this->crearReserva($this->crearTurno($this->crearCancha($this->crearClub($admin))));
        $this->crearReserva($this->crearTurno());
        Sanctum::actingAs($admin);

        $this->getJson('/api/reservas?mis_clubes=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $propia->id);
    }

    public function test_mis_clubes_sin_token_no_devuelve_nada(): void
    {
        $this->crearReserva($this->crearTurno());

        $this->getJson('/api/reservas?mis_clubes=1')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_por_pagina_se_limita_a_100(): void
    {
        $this->getJson('/api/reservas?por_pagina=500')->assertOk()->assertJsonPath('per_page', 100);
        $this->getJson('/api/reservas?por_pagina=25')->assertOk()->assertJsonPath('per_page', 25);
        $this->getJson('/api/reservas')->assertOk()->assertJsonPath('per_page', 10);
    }
}
