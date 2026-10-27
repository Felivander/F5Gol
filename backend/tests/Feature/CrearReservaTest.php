<?php

namespace Tests\Feature;

use App\Models\Turno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\CreaDatosDePrueba;
use Tests\TestCase;

class CrearReservaTest extends TestCase
{
    use CreaDatosDePrueba, RefreshDatabase;

    private function reservar(Turno $turno, array $datos = [], ?User $jugador = null): TestResponse
    {
        $jugador ??= $this->crearJugador();
        Sanctum::actingAs($jugador);

        return $this->postJson('/api/reservas', array_merge([
            'turno_id' => $turno->id,
            'usuario_id' => $jugador->id,
        ], $datos));
    }

    public function test_la_reserva_completa_ocupa_el_turno(): void
    {
        $turno = $this->crearTurno();

        $this->reservar($turno, ['tipo' => 'completa'])
            ->assertCreated()
            ->assertJsonPath('reserva.tipo', 'completa')
            ->assertJsonPath('reserva.estado', 'confirmada');

        $this->assertSame('ocupado', $turno->fresh()->estado);
    }

    public function test_sin_tipo_la_reserva_es_completa(): void
    {
        $turno = $this->crearTurno();

        $this->reservar($turno)->assertCreated();

        $this->assertSame('ocupado', $turno->fresh()->estado);
    }

    public function test_no_deja_reservar_dos_veces_el_mismo_turno(): void
    {
        $turno = $this->crearTurno();
        $this->reservar($turno, ['tipo' => 'completa'])->assertCreated();

        $this->reservar($turno, ['tipo' => 'completa'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'El turno ya no está disponible');

        $this->assertSame(1, $turno->reservas()->count());
    }

    public function test_no_deja_anotarse_en_un_turno_ocupado_por_una_reserva_completa(): void
    {
        $turno = $this->crearTurno();
        $this->reservar($turno, ['tipo' => 'completa'])->assertCreated();

        $this->reservar($turno, ['tipo' => 'individual', 'cantidad_jugadores' => 1])
            ->assertStatus(409)
            ->assertJsonPath('message', 'El turno ya no está disponible');
    }

    public function test_no_deja_reservar_un_turno_bloqueado(): void
    {
        $turno = $this->crearTurno(null, ['estado' => 'bloqueado']);

        $this->reservar($turno, ['tipo' => 'completa'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'El turno ya no está disponible');
        $this->reservar($turno, ['tipo' => 'individual'])->assertStatus(409);

        $this->assertSame(0, $turno->reservas()->count());
        $this->assertSame('bloqueado', $turno->fresh()->estado);
    }

    public function test_el_matchmaking_suma_al_cupo_sin_ocupar_el_turno(): void
    {
        $turno = $this->crearTurno();

        $this->reservar($turno, ['tipo' => 'individual', 'cantidad_jugadores' => 2])->assertCreated();
        $this->reservar($turno, ['tipo' => 'individual', 'cantidad_jugadores' => 1])->assertCreated();

        $turno->refresh();
        $this->assertSame(3, $turno->cupoActual());
        $this->assertSame('disponible', $turno->estado);
    }

    public function test_al_completar_el_cupo_el_turno_queda_ocupado(): void
    {
        $turno = $this->crearTurno();
        $this->assertSame(5, $turno->fresh()->cupo_maximo);

        $this->reservar($turno, ['tipo' => 'individual', 'cantidad_jugadores' => 3])->assertCreated();
        $this->assertSame('disponible', $turno->fresh()->estado);

        $this->reservar($turno, ['tipo' => 'individual', 'cantidad_jugadores' => 2])->assertCreated();

        $turno->refresh();
        $this->assertSame(5, $turno->cupoActual());
        $this->assertSame('ocupado', $turno->estado);

        // Con el cupo lleno ya no entra nadie más
        $this->reservar($turno, ['tipo' => 'individual', 'cantidad_jugadores' => 1])
            ->assertStatus(409)
            ->assertJsonPath('message', 'El turno ya no está disponible');
    }

    public function test_no_deja_anotar_mas_jugadores_que_el_cupo_disponible(): void
    {
        $turno = $this->crearTurno();
        $this->reservar($turno, ['tipo' => 'individual', 'cantidad_jugadores' => 4])->assertCreated();

        $this->reservar($turno, ['tipo' => 'individual', 'cantidad_jugadores' => 2])
            ->assertStatus(409)
            ->assertJsonPath('message', 'No hay cupo suficiente en este turno');

        $turno->refresh();
        $this->assertSame(4, $turno->cupoActual());
        $this->assertSame('disponible', $turno->estado);
    }

    public function test_no_deja_anotar_de_una_vez_mas_jugadores_que_el_cupo_maximo(): void
    {
        $turno = $this->crearTurno();

        $this->reservar($turno, ['tipo' => 'individual', 'cantidad_jugadores' => 6])
            ->assertStatus(409)
            ->assertJsonPath('message', 'No hay cupo suficiente en este turno');

        $this->assertSame(0, $turno->reservas()->count());
    }

    public function test_un_turno_inexistente_responde_422_con_error_en_turno(): void
    {
        // La regla exists:turnos,id del ReservaRequest corta antes de llegar
        // al 404 "Turno no encontrado" del controlador.
        $this->reservar($this->crearTurno(), ['turno_id' => 999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['turno_id' => 'El turno indicado no existe.']);
    }

    public function test_rechaza_un_tipo_invalido(): void
    {
        $turno = $this->crearTurno();

        $this->reservar($turno, ['tipo' => 'grupal'])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'tipo' => 'El campo tipo tiene que ser uno de estos valores: completa, individual.',
            ]);

        $this->assertSame('disponible', $turno->fresh()->estado);
    }

    public function test_rechaza_cantidad_de_jugadores_fuera_de_rango(): void
    {
        $turno = $this->crearTurno();

        $this->reservar($turno, ['tipo' => 'individual', 'cantidad_jugadores' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'cantidad_jugadores' => 'El campo cantidad de jugadores tiene que ser como mínimo 1.',
            ]);

        $this->reservar($turno, ['tipo' => 'individual', 'cantidad_jugadores' => 23])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'cantidad_jugadores' => 'El campo cantidad de jugadores supera el máximo permitido (22).',
            ]);

        $this->reservar($turno, ['tipo' => 'individual', 'cantidad_jugadores' => 'dos'])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'cantidad_jugadores' => 'El campo cantidad de jugadores tiene que ser un número entero.',
            ]);

        $this->assertSame(0, $turno->reservas()->count());
    }

    public function test_sin_token_responde_401(): void
    {
        $turno = $this->crearTurno();

        $this->postJson('/api/reservas', [
            'turno_id' => $turno->id,
            'usuario_id' => $this->crearJugador()->id,
        ])->assertUnauthorized();

        $this->assertSame(0, $turno->reservas()->count());
        $this->assertSame('disponible', $turno->fresh()->estado);
    }
}
