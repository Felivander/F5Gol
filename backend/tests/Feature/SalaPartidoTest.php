<?php

namespace Tests\Feature;

use App\Models\Turno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\CreaDatosDePrueba;
use Tests\TestCase;

class SalaPartidoTest extends TestCase
{
    use CreaDatosDePrueba, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function anotar(Turno $turno, User $jugador, int $cantidad = 1)
    {
        return $this->crearReserva($turno, ['tipo' => 'individual', 'usuario_id' => $jugador->id, 'cantidad_jugadores' => $cantidad]);
    }

    public function test_un_jugador_anotado_ve_la_sala_con_los_demas_sin_sus_mails(): void
    {
        $turno = $this->crearTurno(null, ['cupo_maximo' => 5]);
        $yo = User::factory()->create(['rol' => 'jugador', 'name' => 'Julián Dufey']);
        $otro = User::factory()->create(['rol' => 'jugador', 'name' => 'Lautaro Meza']);
        $this->anotar($turno, $otro, 2);
        $mia = $this->anotar($turno, $yo);

        Sanctum::actingAs($yo);
        $respuesta = $this->getJson("/api/partidos/{$turno->id}")
            ->assertOk()
            ->assertJsonPath('estado', 'armandose')
            ->assertJsonPath('anotados', 3)
            ->assertJsonPath('faltan', 2)
            ->assertJsonPath('club.nombre', 'Club de prueba')
            ->assertJsonCount(2, 'jugadores')
            ->assertJsonPath('jugadores.0.nombre', 'Lautaro M.')
            ->assertJsonPath('jugadores.0.cantidad', 2)
            ->assertJsonPath('jugadores.0.soy_yo', false)
            ->assertJsonPath('jugadores.1.nombre', 'Julián D.')
            ->assertJsonPath('jugadores.1.soy_yo', true)
            ->assertJsonPath('mi_reserva.id', $mia->id)
            ->assertJsonPath('mi_reserva.puede_cancelar', true);

        $this->assertStringNotContainsString($otro->email, $respuesta->getContent());
    }

    public function test_quien_no_esta_anotado_no_ve_la_sala(): void
    {
        $turno = $this->crearTurno();
        $this->anotar($turno, $this->crearJugador());

        Sanctum::actingAs($this->crearJugador());
        $this->getJson("/api/partidos/{$turno->id}")
            ->assertForbidden()
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Solo los jugadores anotados'));
    }

    public function test_el_administrador_del_club_ve_la_sala_y_otro_administrador_no(): void
    {
        $dueno = $this->crearAdministrador();
        $turno = $this->crearTurno($this->crearCancha($this->crearClub($dueno)));
        $this->anotar($turno, $this->crearJugador());

        Sanctum::actingAs($dueno);
        $this->getJson("/api/partidos/{$turno->id}")
            ->assertOk()
            ->assertJsonPath('mi_reserva', null);

        Sanctum::actingAs($this->crearAdministrador());
        $this->getJson("/api/partidos/{$turno->id}")->assertForbidden();
    }

    public function test_pide_sesion(): void
    {
        $turno = $this->crearTurno();
        $this->anotar($turno, $this->crearJugador());

        $this->getJson("/api/partidos/{$turno->id}")->assertUnauthorized();
    }

    public function test_responde_404_si_el_turno_no_existe_o_no_tiene_matchmaking(): void
    {
        Sanctum::actingAs($this->crearJugador());
        $this->getJson('/api/partidos/999')
            ->assertNotFound()
            ->assertJsonPath('message', 'No existe un turno con el id 999.');

        $turno = $this->crearTurno();
        $this->crearReserva($turno); // reserva completa, no es un partido de matchmaking
        $this->getJson("/api/partidos/{$turno->id}")->assertNotFound();
    }

    public function test_las_cancelaciones_no_cuentan_y_el_estado_sigue_al_cupo_y_al_horario(): void
    {
        $turno = $this->crearTurno(null, ['cupo_maximo' => 2]);
        $yo = $this->crearJugador();
        $this->anotar($turno, $yo);
        $this->crearReserva($turno, ['tipo' => 'individual', 'estado' => 'cancelada']);

        Sanctum::actingAs($yo);
        $this->getJson("/api/partidos/{$turno->id}")
            ->assertJsonPath('anotados', 1)
            ->assertJsonCount(1, 'jugadores');

        $this->anotar($turno, $this->crearJugador());
        $this->getJson("/api/partidos/{$turno->id}")->assertJsonPath('estado', 'completo');

        // A una hora del partido ya no se puede cancelar; después de la hora, ya empezó
        Carbon::setTestNow(Carbon::parse($turno->fecha.' 19:00'));
        $this->getJson("/api/partidos/{$turno->id}")->assertJsonPath('mi_reserva.puede_cancelar', false);

        Carbon::setTestNow(Carbon::parse($turno->fecha.' 20:30'));
        $this->getJson("/api/partidos/{$turno->id}")->assertJsonPath('estado', 'ya_empezo');
    }
}
