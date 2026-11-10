<?php

namespace Tests\Feature;

use App\Models\Club;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreaDatosDePrueba;
use Tests\TestCase;

class BuscarClubesTest extends TestCase
{
    use CreaDatosDePrueba, RefreshDatabase;

    private function cargarClubes(): void
    {
        $admin = $this->crearAdministrador();

        Club::create(['administrador_id' => $admin->id, 'nombre' => 'La Bombonera Fútbol 5', 'direccion' => 'Av. San Lorenzo 120']);
        Club::create(['administrador_id' => $admin->id, 'nombre' => 'El Potrero', 'direccion' => 'Barrio Norte, calle 5']);
        Club::create(['administrador_id' => $admin->id, 'nombre' => 'Complejo Norte', 'direccion' => 'Ruta 14 km 3']);
    }

    public function test_sin_buscar_lista_todos_los_clubes(): void
    {
        $this->cargarClubes();

        $this->getJson('/api/clubs')
            ->assertOk()
            ->assertJsonPath('total', 3);
    }

    public function test_busca_por_nombre_sin_importar_mayusculas(): void
    {
        $this->cargarClubes();

        $this->getJson('/api/clubs?buscar=bombonera')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.nombre', 'La Bombonera Fútbol 5');
    }

    public function test_busca_por_nombre_o_direccion(): void
    {
        $this->cargarClubes();

        // "Norte" aparece en la dirección de El Potrero y en el nombre de Complejo Norte
        $respuesta = $this->getJson('/api/clubs?buscar=Norte')->assertOk();

        $this->assertEqualsCanonicalizing(
            ['El Potrero', 'Complejo Norte'],
            array_column($respuesta->json('data'), 'nombre')
        );
    }

    public function test_sin_resultados_devuelve_lista_vacia(): void
    {
        $this->cargarClubes();

        $this->getJson('/api/clubs?buscar=inexistente')
            ->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonCount(0, 'data');
    }

    public function test_los_links_de_paginado_conservan_la_busqueda(): void
    {
        $this->cargarClubes();

        $this->getJson('/api/clubs?buscar=Norte')
            ->assertOk()
            ->assertJsonPath('first_page_url', url('/api/clubs').'?buscar=Norte&page=1');
    }
}
