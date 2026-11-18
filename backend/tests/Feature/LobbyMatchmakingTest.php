<?php

namespace Tests\Feature;

use App\Models\Turno;
use App\Notifications\PartidoIncompleto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\CreaDatosDePrueba;
use Tests\TestCase;

class LobbyMatchmakingTest extends TestCase
{
    use CreaDatosDePrueba, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function anotar(Turno $turno, int $cantidad)
    {
        return $this->crearReserva($turno, ['tipo' => 'individual', 'cantidad_jugadores' => $cantidad]);
    }

    // ---------------- Lobby ----------------

    public function test_el_lobby_muestra_los_partidos_que_se_estan_armando_con_los_lugares_que_faltan(): void
    {
        $turno = $this->crearTurno(null, ['cupo_maximo' => 5]);
        $this->anotar($turno, 2);

        $this->getJson('/api/partidos-abiertos')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.turno_id', $turno->id)
            ->assertJsonPath('0.anotados', 2)
            ->assertJsonPath('0.faltan', 3)
            ->assertJsonPath('0.club.nombre', 'Club de prueba')
            ->assertJsonPath('0.precio_por_jugador', 4000)
            // No expone quiénes están anotados
            ->assertJsonMissingPath('0.jugadores')
            ->assertJsonMissingPath('0.reservas');
    }

    public function test_el_lobby_no_muestra_turnos_sin_anotados_completos_reservados_ni_pasados(): void
    {
        $cancha = $this->crearCancha();
        $this->crearTurno($cancha); // sin nadie
        $completo = $this->crearTurno($cancha, ['hora_inicio' => '21:00', 'hora_fin' => '22:00', 'cupo_maximo' => 2]);
        $this->anotar($completo, 2);
        $completo->update(['estado' => 'ocupado']);
        $pasado = $this->crearTurno($cancha, ['fecha' => now()->subDay()->toDateString()]);
        $this->anotar($pasado, 1);

        $this->getJson('/api/partidos-abiertos')->assertOk()->assertJsonCount(0);
    }

    public function test_el_lobby_ordena_primero_los_que_estan_por_completarse_y_filtra_por_club(): void
    {
        $club = $this->crearClub();
        $cancha = $this->crearCancha($club);
        $lejos = $this->crearTurno($cancha, ['cupo_maximo' => 5]);
        $this->anotar($lejos, 1);
        $cerca = $this->crearTurno($cancha, ['hora_inicio' => '21:00', 'hora_fin' => '22:00', 'cupo_maximo' => 5]);
        $this->anotar($cerca, 4);
        $otroClub = $this->crearTurno(null, ['cupo_maximo' => 5]);
        $this->anotar($otroClub, 3);

        $this->getJson("/api/partidos-abiertos?club_id={$club->id}")
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.turno_id', $cerca->id)
            ->assertJsonPath('1.turno_id', $lejos->id);
    }

    // ---------------- Aviso de partido incompleto ----------------

    public function test_a_la_hora_avisa_a_los_anotados_si_el_partido_no_se_completo(): void
    {
        Notification::fake();
        $turno = $this->crearTurno(null, ['fecha' => '2026-12-07', 'hora_inicio' => '20:00', 'hora_fin' => '21:00', 'cupo_maximo' => 5]);
        $ana = $this->anotar($turno, 2)->usuario;
        $beto = $this->anotar($turno, 1)->usuario;

        // Antes de la hora, nada
        Carbon::setTestNow('2026-12-07 19:55:00');
        $this->artisan('partidos:avisar-incompletos')->assertSuccessful();
        Notification::assertNothingSent();

        // A la hora, avisa a los dos y no repite
        Carbon::setTestNow('2026-12-07 20:00:00');
        $this->artisan('partidos:avisar-incompletos')->expectsOutput('Se avisó 1 partido incompleto.');
        $this->artisan('partidos:avisar-incompletos')->expectsOutput('Se avisaron 0 partidos incompletos.');

        Notification::assertSentTo([$ana, $beto], PartidoIncompleto::class);
        Notification::assertCount(2);
        $this->assertNotNull($turno->fresh()->aviso_incompleto_at);
    }

    public function test_no_avisa_partidos_completos_ni_reservas_de_cancha_entera(): void
    {
        Notification::fake();
        $completo = $this->crearTurno(null, ['fecha' => '2026-12-07', 'hora_inicio' => '20:00', 'hora_fin' => '21:00', 'cupo_maximo' => 2]);
        $this->anotar($completo, 2);
        $completo->update(['estado' => 'ocupado']);
        $entero = $this->crearTurno($completo->cancha, ['fecha' => '2026-12-07', 'hora_inicio' => '19:00', 'hora_fin' => '20:00']);
        $this->crearReserva($entero, ['tipo' => 'completa']);

        Carbon::setTestNow('2026-12-07 20:10:00');
        $this->artisan('partidos:avisar-incompletos')->expectsOutput('Se avisaron 0 partidos incompletos.');

        Notification::assertNothingSent();
    }

    public function test_el_aviso_de_incompleto_sale_por_n8n_si_esta_configurado(): void
    {
        config([
            'services.n8n.webhook_partido_incompleto' => 'http://n8n.test/webhook/f5gol-partido-incompleto',
            'services.n8n.secreto' => 'secreto',
        ]);
        Http::fake(['http://n8n.test/*' => Http::response(['ok' => true])]);
        Notification::fake();
        $turno = $this->crearTurno(null, ['fecha' => '2026-12-07', 'hora_inicio' => '20:00', 'hora_fin' => '21:00', 'cupo_maximo' => 5]);
        $this->anotar($turno, 3);

        Carbon::setTestNow('2026-12-07 20:00:00');
        $this->artisan('partidos:avisar-incompletos')->assertSuccessful();

        Http::assertSent(fn (Request $p) => $p['evento'] === 'partido.incompleto'
            && $p['turno']['anotados'] === 3
            && $p['turno']['cupo_maximo'] === 5
            && count($p['jugadores']) === 1);
        Notification::assertNothingSent();
    }
}
