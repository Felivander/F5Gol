<?php

namespace Tests\Feature;

use App\Models\Turno;
use App\Models\User;
use App\Notifications\PartidoCompleto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\CreaDatosDePrueba;
use Tests\TestCase;

class AvisoPartidoCompletoTest extends TestCase
{
    use CreaDatosDePrueba, RefreshDatabase;

    private const WEBHOOK = 'http://n8n.test/webhook/f5gol-partido-completo';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.n8n.webhook_partido_completo' => self::WEBHOOK,
            'services.n8n.secreto' => 'secreto-de-prueba',
            'services.frontend.url' => 'http://localhost:4200',
        ]);
        Notification::fake();
    }

    private function anotar(Turno $turno, User $jugador, int $cantidad)
    {
        Sanctum::actingAs($jugador);

        return $this->postJson('/api/reservas', [
            'turno_id' => $turno->id,
            'usuario_id' => $jugador->id,
            'tipo' => 'individual',
            'cantidad_jugadores' => $cantidad,
        ]);
    }

    public function test_al_completar_el_partido_avisa_a_n8n_con_todos_los_jugadores(): void
    {
        Http::fake([self::WEBHOOK => Http::response(['ok' => true])]);
        $turno = $this->crearTurno(null, ['cupo_maximo' => 5]);
        $ana = $this->crearJugador();
        $beto = $this->crearJugador();

        $this->anotar($turno, $ana, 3)->assertCreated();
        Http::assertNothingSent();

        $this->anotar($turno, $beto, 2)->assertCreated();

        Http::assertSent(function (Request $pedido) use ($ana, $beto, $turno) {
            $emails = collect($pedido['jugadores'])->pluck('email')->all();

            return $pedido->url() === self::WEBHOOK
                && $pedido->hasHeader('X-F5GOL-Secreto', 'secreto-de-prueba')
                && $pedido['evento'] === 'partido.completo'
                && $pedido['turno']['id'] === $turno->id
                && $pedido['turno']['hora_inicio'] === '20:00'
                && $pedido['club']['nombre'] === 'Club de prueba'
                && $pedido['cancha']['nombre'] === 'Cancha 1'
                && $emails === [$ana->email, $beto->email]
                && $pedido['jugadores'][0]['cantidad_jugadores'] === 3
                && $pedido['links']['mis_reservas'] === 'http://localhost:4200/mis-reservas';
        });
        // Si n8n respondió bien, Laravel no manda otro mail
        Notification::assertNothingSent();
    }

    public function test_si_n8n_falla_el_aviso_sale_por_laravel(): void
    {
        Http::fake(fn () => throw new ConnectionException('n8n apagado'));
        $turno = $this->crearTurno(null, ['cupo_maximo' => 2]);
        $ana = $this->crearJugador();
        $beto = $this->crearJugador();

        $this->anotar($turno, $ana, 1)->assertCreated();
        $this->anotar($turno, $beto, 1)->assertCreated();

        Notification::assertSentTo([$ana, $beto], PartidoCompleto::class);
    }

    public function test_sin_n8n_configurado_el_aviso_sale_por_laravel(): void
    {
        config(['services.n8n.webhook_partido_completo' => null]);
        Http::fake();
        $turno = $this->crearTurno(null, ['cupo_maximo' => 2]);
        $ana = $this->crearJugador();
        $beto = $this->crearJugador();

        $this->anotar($turno, $ana, 1)->assertCreated();
        $this->anotar($turno, $beto, 1)->assertCreated();

        Http::assertNothingSent();
        Notification::assertSentTo([$ana, $beto], PartidoCompleto::class);
    }

    public function test_el_mail_de_laravel_usa_el_tema_de_la_app_y_sale_en_espanol(): void
    {
        $turno = $this->crearTurno(null, ['cupo_maximo' => 2]);
        $ana = $this->crearJugador();
        $this->crearReserva($turno, ['usuario_id' => $ana->id, 'tipo' => 'individual', 'equipo' => 'A']);

        $html = (string) (new PartidoCompleto($turno))->toMail($ana)->render();

        // Colores de resources/views/mail/f5gol.css: fondo papel y texto en tinta
        $this->assertStringContainsString('#f5f2ea', $html);
        $this->assertStringContainsString('#15171a', $html);
        $this->assertStringContainsString('Saludos,', $html);
        $this->assertStringNotContainsString('Regards', $html);
        $this->assertStringContainsString('Jugás en el Equipo A.', $html);
    }

    public function test_una_reserva_completa_no_dispara_el_aviso_de_matchmaking(): void
    {
        Http::fake();
        $turno = $this->crearTurno();
        $jugador = $this->crearJugador();
        Sanctum::actingAs($jugador);

        $this->postJson('/api/reservas', ['turno_id' => $turno->id, 'usuario_id' => $jugador->id, 'tipo' => 'completa'])
            ->assertCreated();

        Http::assertNothingSent();
        Notification::assertNothingSent();
    }
}
