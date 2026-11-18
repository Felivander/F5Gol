<?php

namespace Tests\Feature;

use App\Models\Turno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\CreaDatosDePrueba;
use Tests\TestCase;

/**
 * El partido de matchmaking tiene dos equipos. Se entra de tres formas:
 * un lugar suelto, un equipo entero (la mitad de la cancha) o la cancha entera.
 */
class EquiposPartidoTest extends TestCase
{
    use CreaDatosDePrueba, RefreshDatabase;

    private function reservar(Turno $turno, array $datos, ?User $jugador = null)
    {
        $jugador ??= $this->crearJugador();
        Sanctum::actingAs($jugador);

        return $this->postJson('/api/reservas', array_merge([
            'turno_id' => $turno->id,
            'usuario_id' => $jugador->id,
        ], $datos));
    }

    public function test_un_equipo_entero_ocupa_la_mitad_de_la_cancha_y_el_otro_queda_libre(): void
    {
        $turno = $this->crearTurno();

        $this->reservar($turno, ['tipo' => 'equipo'])
            ->assertCreated()
            ->assertJsonPath('reserva.equipo', 'A')
            ->assertJsonPath('reserva.cantidad_jugadores', 5)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Reservaste el Equipo A entero (5 jugadores)'));

        $this->assertSame('disponible', $turno->fresh()->estado);
        $this->assertSame(5, $turno->fresh()->libresEquipo('B'));

        // El segundo equipo entero va al B y completa el partido
        $this->reservar($turno, ['tipo' => 'equipo'])
            ->assertCreated()
            ->assertJsonPath('reserva.equipo', 'B');
        $this->assertSame('ocupado', $turno->fresh()->estado);
    }

    public function test_no_se_puede_reservar_entero_un_equipo_que_ya_tiene_jugadores(): void
    {
        $turno = $this->crearTurno();
        $this->reservar($turno, ['tipo' => 'individual', 'equipo' => 'A'])->assertCreated();

        $this->reservar($turno, ['tipo' => 'equipo', 'equipo' => 'A'])
            ->assertStatus(409)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'El Equipo A del turno') && str_contains($m, 'ya tiene 1 jugador'));

        // Sin elegir equipo, toma el B que está vacío
        $this->reservar($turno, ['tipo' => 'equipo'])->assertCreated()->assertJsonPath('reserva.equipo', 'B');

        // Con los dos equipos con gente, ya no hay equipo entero para reservar
        $this->reservar($turno, ['tipo' => 'equipo'])
            ->assertStatus(409)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Los dos equipos'));
    }

    public function test_un_lugar_suelto_sin_equipo_va_al_que_tiene_mas_lugares_libres(): void
    {
        $turno = $this->crearTurno();
        $this->reservar($turno, ['tipo' => 'individual', 'cantidad_jugadores' => 2, 'equipo' => 'A'])->assertCreated();

        $this->reservar($turno, ['tipo' => 'individual'])
            ->assertCreated()
            ->assertJsonPath('reserva.equipo', 'B')
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'en el Equipo B'));
    }

    public function test_la_cancha_entera_no_se_puede_reservar_si_hay_un_equipo_anotado(): void
    {
        $turno = $this->crearTurno();
        $this->reservar($turno, ['tipo' => 'equipo'])->assertCreated();

        $this->reservar($turno, ['tipo' => 'completa'])->assertStatus(409);
    }

    public function test_en_futbol_7_el_equipo_es_de_7(): void
    {
        $admin = $this->crearAdministrador();
        $cancha = $this->crearCancha($this->crearClub($admin));
        $cancha->update(['tipo' => 'futbol7', 'capacidad_jugadores' => 14]);
        $turno = $this->crearTurno($cancha, ['cupo_maximo' => 14]);

        $this->reservar($turno, ['tipo' => 'equipo'])
            ->assertCreated()
            ->assertJsonPath('reserva.cantidad_jugadores', 7);
    }

    public function test_el_lobby_dice_si_ya_estoy_anotado_y_los_lugares_de_cada_equipo(): void
    {
        $turno = $this->crearTurno();
        $yo = $this->crearJugador();
        $this->reservar($turno, ['tipo' => 'individual', 'equipo' => 'B'], $yo)->assertCreated();

        // Sin token, nadie está anotado
        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => ''])->getJson('/api/partidos-abiertos')
            ->assertOk()
            ->assertJsonPath('0.estoy_anotado', false)
            ->assertJsonPath('0.equipos.A.faltan', 5)
            ->assertJsonPath('0.equipos.B.anotados', 1);

        // Con el token del jugador
        $token = $yo->createToken('prueba')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/partidos-abiertos')
            ->assertOk()
            ->assertJsonPath('0.estoy_anotado', true);
    }

    public function test_la_sala_muestra_el_equipo_de_cada_jugador(): void
    {
        $turno = $this->crearTurno();
        $yo = $this->crearJugador();
        $this->reservar($turno, ['tipo' => 'equipo', 'equipo' => 'A'])->assertCreated();
        $this->reservar($turno, ['tipo' => 'individual'], $yo)->assertCreated();

        Sanctum::actingAs($yo);
        $this->getJson("/api/partidos/{$turno->id}")
            ->assertOk()
            ->assertJsonPath('equipos.A.faltan', 0)
            ->assertJsonPath('equipos.B.anotados', 1)
            ->assertJsonPath('jugadores.0.equipo', 'A')
            ->assertJsonPath('jugadores.0.tipo', 'equipo')
            ->assertJsonPath('jugadores.1.equipo', 'B')
            ->assertJsonPath('mi_reserva.equipo', 'B');
    }
}
