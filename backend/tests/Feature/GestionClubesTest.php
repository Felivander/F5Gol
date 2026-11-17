<?php

namespace Tests\Feature;

use App\Models\Cancha;
use App\Models\Club;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\CreaDatosDePrueba;
use Tests\TestCase;

class GestionClubesTest extends TestCase
{
    use CreaDatosDePrueba, RefreshDatabase;

    private function datosClub(array $cambios = []): array
    {
        return array_merge([
            'nombre' => 'Complejo La Bombonera',
            'direccion' => 'San Martín 123',
            'telefono' => '345 4123456',
            'descripcion' => 'Cuatro canchas, buffet y estacionamiento.',
        ], $cambios);
    }

    public function test_el_administrador_crea_su_club_y_queda_como_dueno(): void
    {
        $admin = $this->crearAdministrador();
        $otro = $this->crearAdministrador();

        // Aunque mande otro dueño, el club queda a nombre de quien lo crea
        $this->actingAs($admin)
            ->postJson('/api/clubs', $this->datosClub(['administrador_id' => $otro->id]))
            ->assertCreated()
            ->assertJsonPath('club.administrador_id', $admin->id)
            ->assertJsonPath('club.descripcion', 'Cuatro canchas, buffet y estacionamiento.');

        $this->assertSame(1, Club::where('administrador_id', $admin->id)->count());
        $this->assertSame(0, Club::where('administrador_id', $otro->id)->count());
    }

    public function test_valida_nombre_y_telefono(): void
    {
        $this->actingAs($this->crearAdministrador())
            ->postJson('/api/clubs', $this->datosClub(['nombre' => '', 'telefono' => 'llamame']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre', 'telefono']);
    }

    public function test_un_administrador_no_puede_editar_ni_borrar_el_club_de_otro(): void
    {
        $club = $this->crearClub();
        $intruso = $this->crearAdministrador();

        $this->actingAs($intruso)->putJson("/api/clubs/{$club->id}", ['nombre' => 'Hackeado'])->assertForbidden();
        $this->actingAs($intruso)->deleteJson("/api/clubs/{$club->id}")->assertForbidden();

        $this->assertSame('Club de prueba', $club->fresh()->nombre);
    }

    public function test_el_dueno_edita_su_club(): void
    {
        $admin = $this->crearAdministrador();
        $club = $this->crearClub($admin);

        $this->actingAs($admin)
            ->putJson("/api/clubs/{$club->id}", ['nombre' => 'Nuevo nombre', 'descripcion' => 'Con parrilla'])
            ->assertOk()
            ->assertJsonPath('club.nombre', 'Nuevo nombre')
            ->assertJsonPath('club.descripcion', 'Con parrilla');
    }

    public function test_no_se_puede_borrar_un_club_con_canchas_activas(): void
    {
        $admin = $this->crearAdministrador();
        $club = $this->crearClub($admin);
        $this->crearCancha($club);

        $this->actingAs($admin)->deleteJson("/api/clubs/{$club->id}")->assertStatus(409);
        $this->assertNotNull($club->fresh());
    }

    public function test_se_puede_borrar_un_club_con_canchas_eliminadas_sin_reservas(): void
    {
        $admin = $this->crearAdministrador();
        $club = $this->crearClub($admin);
        $cancha = $this->crearCancha($club);
        $this->crearTurno($cancha);
        $cancha->delete();

        $this->actingAs($admin)->deleteJson("/api/clubs/{$club->id}")->assertOk();
        $this->assertNull($club->fresh());
        $this->assertNull(Cancha::withTrashed()->find($cancha->id));
    }

    public function test_no_se_puede_borrar_un_club_si_sus_canchas_eliminadas_tienen_reservas(): void
    {
        $admin = $this->crearAdministrador();
        $club = $this->crearClub($admin);
        $cancha = $this->crearCancha($club);
        $this->crearReserva($this->crearTurno($cancha));
        $cancha->delete();

        $this->actingAs($admin)->deleteJson("/api/clubs/{$club->id}")->assertStatus(409);
        $this->assertNotNull($club->fresh());
    }

    public function test_el_dueno_borra_un_club_sin_canchas(): void
    {
        $admin = $this->crearAdministrador();
        $club = $this->crearClub($admin);

        $this->actingAs($admin)->deleteJson("/api/clubs/{$club->id}")->assertOk();
        $this->assertNull($club->fresh());
    }

    public function test_mis_clubes_devuelve_solo_los_del_administrador_con_la_cantidad_de_canchas(): void
    {
        $admin = $this->crearAdministrador();
        $mio = $this->crearClub($admin);
        $this->crearCancha($mio);
        $this->crearCancha($mio)->update(['estado' => 'inactiva']);
        $this->crearClub(); // de otro administrador

        $this->actingAs($admin)->getJson('/api/clubs?mios=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mio->id)
            // El dueño cuenta también la inactiva
            ->assertJsonPath('data.0.canchas_count', 2);
    }

    public function test_el_listado_publico_cuenta_solo_canchas_activas(): void
    {
        $club = $this->crearClub();
        $this->crearCancha($club);
        $this->crearCancha($club)->update(['estado' => 'inactiva']);

        $this->getJson('/api/clubs')->assertJsonPath('data.0.canchas_count', 1);
    }

    public function test_mis_clubes_sin_sesion_responde_401(): void
    {
        $this->getJson('/api/clubs?mios=1')->assertUnauthorized();
    }

    public function test_el_dueno_sube_y_borra_fotos_del_club(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('Falta la extensión GD de PHP para generar imágenes de prueba.');
        }

        Storage::fake('public');
        $admin = $this->crearAdministrador();
        $club = $this->crearClub($admin);

        $this->actingAs($admin)
            ->post("/api/clubs/{$club->id}/fotos", ['foto' => UploadedFile::fake()->image('frente.jpg')], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonCount(1, 'club.fotos');

        $this->actingAs($admin)->deleteJson("/api/clubs/{$club->id}/fotos/0")
            ->assertOk()
            ->assertJsonCount(0, 'club.fotos');

        $this->actingAs($admin)->deleteJson("/api/clubs/{$club->id}/fotos/0")->assertNotFound();
    }

    public function test_otro_administrador_no_puede_subir_fotos_al_club(): void
    {
        $club = $this->crearClub();

        $this->actingAs($this->crearAdministrador())
            ->post("/api/clubs/{$club->id}/fotos", ['foto' => UploadedFile::fake()->create('foto.jpg', 10, 'image/jpeg')], ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    public function test_la_cancha_guarda_superficie_y_comodidades(): void
    {
        $admin = $this->crearAdministrador();
        $club = $this->crearClub($admin);

        $this->actingAs($admin)->postJson('/api/canchas', [
            'club_id' => $club->id,
            'nombre' => 'Cancha 1',
            'tarifa' => 30000,
            'superficie' => 'cesped_sintetico',
            'techada' => true,
            'iluminacion' => true,
            'vestuarios' => false,
        ])
            ->assertCreated()
            ->assertJsonPath('cancha.superficie', 'cesped_sintetico')
            ->assertJsonPath('cancha.techada', true)
            ->assertJsonPath('cancha.iluminacion', true)
            ->assertJsonPath('cancha.vestuarios', false);
    }

    public function test_rechaza_una_superficie_que_no_existe(): void
    {
        $admin = $this->crearAdministrador();
        $club = $this->crearClub($admin);

        $this->actingAs($admin)->postJson('/api/canchas', [
            'club_id' => $club->id,
            'nombre' => 'Cancha 1',
            'tarifa' => 30000,
            'superficie' => 'arena',
        ])->assertUnprocessable()->assertJsonValidationErrors('superficie');
    }
}
