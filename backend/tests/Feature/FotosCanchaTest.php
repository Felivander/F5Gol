<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\CreaDatosDePrueba;
use Tests\TestCase;

class FotosCanchaTest extends TestCase
{
    use CreaDatosDePrueba, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_el_dueno_puede_subir_una_foto(): void
    {
        $admin = $this->crearAdministrador();
        $cancha = $this->crearCancha($this->crearClub($admin));
        Sanctum::actingAs($admin);

        $foto = UploadedFile::fake()->image('cancha.jpg');

        $respuesta = $this->postJson("/api/canchas/{$cancha->id}/fotos", ['foto' => $foto])
            ->assertCreated()
            ->assertJsonCount(1, 'cancha.fotos');

        Storage::disk('public')->assertExists("canchas/{$cancha->id}/{$foto->hashName()}");
        $this->assertSame(
            Storage::disk('public')->url("canchas/{$cancha->id}/{$foto->hashName()}"),
            $respuesta->json('cancha.fotos.0')
        );
    }

    public function test_las_fotos_se_agregan_al_final(): void
    {
        $admin = $this->crearAdministrador();
        $cancha = $this->crearCancha($this->crearClub($admin));
        Sanctum::actingAs($admin);

        $this->postJson("/api/canchas/{$cancha->id}/fotos", ['foto' => UploadedFile::fake()->image('a.png')])->assertCreated();
        $this->postJson("/api/canchas/{$cancha->id}/fotos", ['foto' => UploadedFile::fake()->image('b.webp')])
            ->assertCreated()
            ->assertJsonCount(2, 'cancha.fotos');
    }

    public function test_el_dueno_puede_borrar_una_foto(): void
    {
        $admin = $this->crearAdministrador();
        $cancha = $this->crearCancha($this->crearClub($admin));
        Sanctum::actingAs($admin);

        $primera = UploadedFile::fake()->image('primera.jpg');
        $segunda = UploadedFile::fake()->image('segunda.jpg');
        $this->postJson("/api/canchas/{$cancha->id}/fotos", ['foto' => $primera]);
        $this->postJson("/api/canchas/{$cancha->id}/fotos", ['foto' => $segunda]);

        $this->deleteJson("/api/canchas/{$cancha->id}/fotos/0")
            ->assertOk()
            ->assertJsonCount(1, 'cancha.fotos');

        Storage::disk('public')->assertMissing("canchas/{$cancha->id}/{$primera->hashName()}");
        Storage::disk('public')->assertExists("canchas/{$cancha->id}/{$segunda->hashName()}");
        $this->assertStringEndsWith($segunda->hashName(), $cancha->fresh()->fotos[0]);
    }

    public function test_borrar_una_foto_que_no_existe_devuelve_404(): void
    {
        $admin = $this->crearAdministrador();
        $cancha = $this->crearCancha($this->crearClub($admin));
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/canchas/{$cancha->id}/fotos/0")
            ->assertNotFound()
            ->assertJsonPath('message', 'Foto no encontrada');
    }

    public function test_un_jugador_no_puede_subir_fotos(): void
    {
        $cancha = $this->crearCancha();
        Sanctum::actingAs($this->crearJugador());

        $this->postJson("/api/canchas/{$cancha->id}/fotos", ['foto' => UploadedFile::fake()->image('cancha.jpg')])
            ->assertForbidden();

        $this->assertNull($cancha->fresh()->fotos);
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_otro_administrador_no_puede_subir_fotos(): void
    {
        $cancha = $this->crearCancha();
        Sanctum::actingAs($this->crearAdministrador());

        $this->postJson("/api/canchas/{$cancha->id}/fotos", ['foto' => UploadedFile::fake()->image('cancha.jpg')])
            ->assertForbidden()
            ->assertJsonPath('message', 'Solo podés gestionar las canchas de tus propios clubes.');

        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_otro_administrador_no_puede_borrar_fotos(): void
    {
        $admin = $this->crearAdministrador();
        $cancha = $this->crearCancha($this->crearClub($admin));
        Sanctum::actingAs($admin);
        $this->postJson("/api/canchas/{$cancha->id}/fotos", ['foto' => UploadedFile::fake()->image('cancha.jpg')]);

        Sanctum::actingAs($this->crearAdministrador());

        $this->deleteJson("/api/canchas/{$cancha->id}/fotos/0")->assertForbidden();
        $this->assertCount(1, $cancha->fresh()->fotos);
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_rechaza_un_archivo_que_no_es_imagen(): void
    {
        $admin = $this->crearAdministrador();
        $cancha = $this->crearCancha($this->crearClub($admin));
        Sanctum::actingAs($admin);

        $this->postJson("/api/canchas/{$cancha->id}/fotos", ['foto' => UploadedFile::fake()->create('reglamento.pdf', 100, 'application/pdf')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['foto' => 'La foto tiene que ser una imagen.']);

        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_rechaza_una_foto_de_mas_de_4_mb(): void
    {
        $admin = $this->crearAdministrador();
        $cancha = $this->crearCancha($this->crearClub($admin));
        Sanctum::actingAs($admin);

        $this->postJson("/api/canchas/{$cancha->id}/fotos", ['foto' => UploadedFile::fake()->image('enorme.jpg')->size(5000)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['foto' => 'La foto no puede pesar más de 4 MB.']);
    }

    public function test_al_borrar_la_cancha_se_borran_sus_fotos(): void
    {
        $admin = $this->crearAdministrador();
        $cancha = $this->crearCancha($this->crearClub($admin));
        Sanctum::actingAs($admin);
        $this->postJson("/api/canchas/{$cancha->id}/fotos", ['foto' => UploadedFile::fake()->image('cancha.jpg')]);

        $this->deleteJson("/api/canchas/{$cancha->id}")->assertOk();

        $this->assertEmpty(Storage::disk('public')->allFiles());
    }
}
