<?php

namespace Tests\Feature;

use App\Models\JugadorAnotado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\CreaDatosDePrueba;
use Tests\TestCase;

/**
 * Los errores de la API tienen el código HTTP correcto y un mensaje en español
 * que dice qué pasó, y los permisos de reservas e inscripciones se respetan.
 */
class MensajesApiTest extends TestCase
{
    use CreaDatosDePrueba, RefreshDatabase;

    public function test_404_dice_que_recurso_y_que_id_no_existen(): void
    {
        $this->getJson('/api/clubs/999')->assertNotFound()->assertJsonPath('message', 'No existe un club con el id 999.');
        $this->getJson('/api/canchas/999')->assertNotFound()->assertJsonPath('message', 'No existe una cancha con el id 999.');
        $this->getJson('/api/turnos/999')->assertNotFound()->assertJsonPath('message', 'No existe un turno con el id 999.');
    }

    public function test_404_de_una_ruta_que_no_existe(): void
    {
        $this->getJson('/api/no-existe')
            ->assertNotFound()
            ->assertJsonPath('message', 'La ruta GET /api/no-existe no existe. Revisá la documentación en /api/documentation.');
    }

    public function test_405_en_espanol_con_los_metodos_aceptados(): void
    {
        $this->patchJson('/api/login')
            ->assertStatus(405)
            ->assertJsonPath('message', 'El método PATCH no está permitido en /api/login. Métodos aceptados: POST.')
            ->assertJsonMissingPath('exception');
    }

    public function test_401_distingue_sin_token_de_token_invalido(): void
    {
        $this->getJson('/api/user')
            ->assertUnauthorized()
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Necesitás iniciar sesión'));

        $this->withToken('token-falso')->getJson('/api/user')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Tu sesión no es válida o venció. Iniciá sesión de nuevo.');
    }

    public function test_un_json_mal_escrito_responde_400(): void
    {
        $this->call('POST', '/api/login', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], '{"email": ')
            ->assertStatus(400)
            ->assertJsonPath('message', 'El cuerpo del pedido no es un JSON válido. Revisá comillas, comas y llaves.');
    }

    public function test_422_resume_cuantos_datos_hay_que_corregir(): void
    {
        $this->postJson('/api/register', [])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Hay 4 datos para corregir. El detalle de cada uno está en "errors".')
            ->assertJsonPath('errors.name.0', 'Falta tu nombre.');

        // Con un solo error, el mensaje general es ese error
        Sanctum::actingAs($this->crearAdministrador());
        $this->postJson('/api/clubs', [])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Falta el nombre.');
    }

    public function test_los_mensajes_de_validacion_estan_en_espanol(): void
    {
        $turno = $this->crearTurno();
        Sanctum::actingAs($turno->cancha->club->administrador);

        $this->patchJson("/api/turnos/{$turno->id}/bloqueo", ['bloqueado' => 'tal vez'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.bloqueado.0', 'El campo bloqueado tiene que ser true (bloquear) o false (desbloquear).');
    }

    public function test_el_login_tiene_limite_de_intentos(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/login', ['email' => 'nadie@f5gol.com', 'password' => 'x'])->assertUnauthorized();
        }

        $this->postJson('/api/login', ['email' => 'nadie@f5gol.com', 'password' => 'x'])
            ->assertStatus(429)
            ->assertJsonPath('message', fn ($m) => str_starts_with($m, 'Demasiados intentos seguidos. Esperá'));
    }

    public function test_un_jugador_no_puede_reservar_a_nombre_de_otro(): void
    {
        $turno = $this->crearTurno();
        $yo = $this->crearJugador();
        $otro = $this->crearJugador();
        Sanctum::actingAs($yo);

        $this->postJson('/api/reservas', ['turno_id' => $turno->id, 'usuario_id' => $otro->id])
            ->assertForbidden()
            ->assertJsonPath('message', 'Solo podés reservar a tu nombre: usuario_id tiene que ser tu propio id.');

        $this->assertSame(0, $turno->reservas()->count());
    }

    public function test_el_administrador_del_club_puede_reservar_para_otra_persona(): void
    {
        $turno = $this->crearTurno();
        $jugador = $this->crearJugador();
        Sanctum::actingAs($turno->cancha->club->administrador);

        $this->postJson('/api/reservas', ['turno_id' => $turno->id, 'usuario_id' => $jugador->id])
            ->assertCreated()
            ->assertJsonPath('reserva.usuario_id', $jugador->id)
            ->assertJsonPath('message', fn ($m) => str_starts_with($m, 'Listo: reservaste la cancha entera para el turno del'));
    }

    public function test_no_se_puede_cambiar_el_titular_de_una_reserva(): void
    {
        $reserva = $this->crearReserva($this->crearTurno());
        Sanctum::actingAs($reserva->usuario);

        $this->putJson("/api/reservas/{$reserva->id}", ['usuario_id' => $this->crearJugador()->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['usuario_id' => 'No se puede cambiar el titular de una reserva.']);
    }

    public function test_nadie_ajeno_puede_anotar_ni_borrar_jugadores_de_una_reserva(): void
    {
        $reserva = $this->crearReserva($this->crearTurno(), ['tipo' => 'individual']);
        $anotado = JugadorAnotado::create([
            'reserva_id' => $reserva->id,
            'usuario_id' => $reserva->usuario_id,
            'fecha_inscripcion' => now(),
        ]);
        $intruso = $this->crearJugador();
        Sanctum::actingAs($intruso);

        $this->postJson('/api/jugador-anotados', ['reserva_id' => $reserva->id, 'usuario_id' => $intruso->id])
            ->assertForbidden();
        $this->deleteJson("/api/jugador-anotados/{$anotado->id}")->assertForbidden();
        $this->assertNotNull($anotado->fresh());

        // El que hizo la reserva sí puede
        Sanctum::actingAs($reserva->usuario);
        $this->deleteJson("/api/jugador-anotados/{$anotado->id}")
            ->assertOk()
            ->assertJsonPath('message', 'La inscripción se eliminó.');
    }
}
