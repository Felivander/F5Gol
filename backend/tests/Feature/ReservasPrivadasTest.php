<?php

namespace Tests\Feature;

use App\Models\JugadorAnotado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\CreaDatosDePrueba;
use Tests\TestCase;

/**
 * Las reservas y los jugadores anotados tienen nombres y mails:
 * nadie puede ver los de otro usuario ni los de un club ajeno.
 */
class ReservasPrivadasTest extends TestCase
{
    use CreaDatosDePrueba, RefreshDatabase;

    public function test_sin_sesion_no_se_ven_reservas_ni_jugadores_anotados(): void
    {
        $reserva = $this->crearReserva($this->crearTurno());

        $this->getJson('/api/reservas')->assertUnauthorized();
        $this->getJson("/api/reservas/{$reserva->id}")->assertUnauthorized();
        $this->getJson('/api/jugador-anotados')->assertUnauthorized();
    }

    public function test_el_jugador_ve_solo_sus_reservas_aunque_pida_las_de_otro(): void
    {
        $turno = $this->crearTurno();
        $mia = $this->crearReserva($turno);
        $ajena = $this->crearReserva($this->crearTurno($turno->cancha, ['hora_inicio' => '21:00', 'hora_fin' => '22:00']));
        Sanctum::actingAs($mia->usuario);

        $this->getJson('/api/reservas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mia->id);

        // Cambiar usuario_id en la URL no sirve para espiar a otro
        $this->getJson("/api/reservas?usuario_id={$ajena->usuario_id}")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson("/api/reservas/{$ajena->id}")->assertForbidden();
        $this->getJson("/api/reservas/{$mia->id}")->assertOk();
    }

    public function test_el_administrador_ve_las_reservas_de_sus_canchas_pero_no_las_de_otro_club(): void
    {
        $admin = $this->crearAdministrador();
        $deSuClub = $this->crearReserva($this->crearTurno($this->crearCancha($this->crearClub($admin))));
        $deOtroClub = $this->crearReserva($this->crearTurno());
        Sanctum::actingAs($admin);

        $this->getJson('/api/reservas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $deSuClub->id);

        $this->getJson("/api/reservas/{$deSuClub->id}")->assertOk();
        $this->getJson("/api/reservas/{$deOtroClub->id}")->assertForbidden();
    }

    public function test_jugadores_anotados_solo_los_ve_quien_corresponde(): void
    {
        $reserva = $this->crearReserva($this->crearTurno(), ['tipo' => 'individual']);
        $anotado = JugadorAnotado::create([
            'reserva_id' => $reserva->id,
            'usuario_id' => $reserva->usuario_id,
            'fecha_inscripcion' => now(),
        ]);

        Sanctum::actingAs($this->crearJugador());
        $this->getJson('/api/jugador-anotados')->assertOk()->assertJsonCount(0);
        $this->getJson("/api/jugador-anotados/{$anotado->id}")->assertForbidden();

        Sanctum::actingAs($reserva->usuario);
        $this->getJson('/api/jugador-anotados')->assertOk()->assertJsonCount(1);
        $this->getJson("/api/jugador-anotados/{$anotado->id}")->assertOk();
    }
}
