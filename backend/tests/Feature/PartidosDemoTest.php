<?php

namespace Tests\Feature;

use App\Models\Reserva;
use App\Models\Turno;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PartidosDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PartidosDemoTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK = 'http://n8n.test/webhook/f5gol-partido-completo';

    protected function setUp(): void
    {
        parent::setUp();
        // A la mañana, como el día de la presentación: hay turnos de 15 a 22 hoy y mañana
        $this->travelTo(now()->setTime(10, 0));
        $this->seed(DatabaseSeeder::class);
    }

    // Partidos que todavía no empezaron
    private function partidos(): Collection
    {
        return Turno::whereDate('fecha', '>=', now()->toDateString())
            ->whereHas('reservas', fn ($q) => $q->delPartido())
            ->get()
            ->filter(fn (Turno $t) => $t->inicio()->isFuture())
            ->values();
    }

    private function faltaUno(): Collection
    {
        return $this->partidos()->filter(fn (Turno $t) => $t->estado === 'disponible' && $t->cupoActual() === $t->cupo_maximo - 1)->values();
    }

    private function mitad(): Collection
    {
        return $this->partidos()->filter(fn (Turno $t) => $t->estado === 'disponible'
            && $t->libresEquipo('A') === 0 && $t->anotadosEquipo('B') === 0)->values();
    }

    private function completos(): Collection
    {
        return $this->partidos()->filter(fn (Turno $t) => $t->estado === 'ocupado' && $t->cupoActual() === $t->cupo_maximo)->values();
    }

    public function test_deja_un_partido_en_cada_estado(): void
    {
        $this->assertCount(1, $this->faltaUno());
        $this->assertCount(1, $this->mitad());
        $this->assertCount(1, $this->completos());

        // En canchas distintas, para que en la demo se vean variados
        $canchas = collect([$this->faltaUno()->first(), $this->mitad()->first(), $this->completos()->first()])->pluck('cancha_id');
        $this->assertCount(3, $canchas->unique());
    }

    public function test_el_lobby_muestra_el_que_falta_uno_y_el_de_mitad_pero_no_el_completo(): void
    {
        $lobby = collect($this->getJson('/api/partidos-abiertos')->assertOk()->json())->keyBy('turno_id');

        $faltaUno = $this->faltaUno()->first();
        $mitad = $this->mitad()->first();

        $this->assertSame(1, $lobby[$faltaUno->id]['faltan']);
        $this->assertSame($mitad->cupoEquipo('B'), $lobby[$mitad->id]['faltan']);
        $this->assertFalse($lobby->has($this->completos()->first()->id));
    }

    public function test_al_de_mitad_se_le_puede_reservar_el_otro_equipo_entero(): void
    {
        $turno = $this->mitad()->first();
        $jugador = User::where('email', 'jugador1@f5gol.com')->first();
        Sanctum::actingAs($jugador);

        $this->postJson('/api/reservas', [
            'turno_id' => $turno->id,
            'usuario_id' => $jugador->id,
            'tipo' => 'equipo',
            'equipo' => 'B',
            'cantidad_jugadores' => $turno->cupoEquipo('B'),
        ])->assertCreated();

        $this->assertSame('ocupado', $turno->fresh()->estado);
    }

    public function test_deja_libre_un_turno_vacio_para_arrancar_un_partido(): void
    {
        $vacios = Turno::where('estado', 'disponible')->whereDoesntHave('reservas')->get()
            ->filter(fn (Turno $t) => $t->inicio()->isFuture());

        $this->assertNotEmpty($vacios);
    }

    public function test_usa_los_jugadores_de_demo_y_deja_libres_las_cuentas_jugador(): void
    {
        $ids = $this->faltaUno()->concat($this->mitad())->concat($this->completos())->pluck('id');
        $emails = Reserva::whereIn('turno_id', $ids)->with('usuario:id,email')->get()->pluck('usuario.email')->unique();

        $this->assertNotEmpty($emails);
        $this->assertTrue($emails->every(fn ($email) => str_starts_with($email, 'demo') && str_ends_with($email, '@f5gol.com')));
    }

    public function test_correrlo_otra_vez_no_agrega_partidos(): void
    {
        $reservas = Reserva::count();

        $this->seed(PartidosDemoSeeder::class);

        $this->assertSame($reservas, Reserva::count());
    }

    public function test_si_se_completa_el_que_falta_uno_el_seeder_arma_otro(): void
    {
        $turno = $this->faltaUno()->first();
        $jugador = User::where('email', 'jugador1@f5gol.com')->first();
        Sanctum::actingAs($jugador);
        Http::fake();
        Notification::fake();
        $equipo = $turno->libresEquipo('A') === 1 ? 'A' : 'B';
        $this->postJson('/api/reservas', ['turno_id' => $turno->id, 'usuario_id' => $jugador->id, 'tipo' => 'individual', 'equipo' => $equipo, 'cantidad_jugadores' => 1])
            ->assertCreated();

        $this->seed(PartidosDemoSeeder::class);

        $this->assertCount(1, $this->faltaUno());
        $this->assertNotSame($turno->id, $this->faltaUno()->first()->id);
    }

    public function test_al_sumarse_al_que_falta_uno_se_completa_y_avisa_a_n8n(): void
    {
        config([
            'services.n8n.webhook_partido_completo' => self::WEBHOOK,
            'services.n8n.secreto' => 'secreto-de-prueba',
        ]);
        Http::fake([self::WEBHOOK => Http::response(['ok' => true])]);
        Notification::fake();

        $turno = $this->faltaUno()->first();
        $equipo = $turno->libresEquipo('A') === 1 ? 'A' : 'B';
        $jugador = User::where('email', 'jugador1@f5gol.com')->first();
        Sanctum::actingAs($jugador);

        $this->postJson('/api/reservas', [
            'turno_id' => $turno->id,
            'usuario_id' => $jugador->id,
            'tipo' => 'individual',
            'equipo' => $equipo,
            'cantidad_jugadores' => 1,
        ])->assertCreated();

        $this->assertSame('ocupado', $turno->fresh()->estado);
        Http::assertSent(fn (Request $pedido) => $pedido->url() === self::WEBHOOK
            && $pedido['evento'] === 'partido.completo'
            && $pedido['turno']['id'] === $turno->id
            && collect($pedido['jugadores'])->pluck('email')->contains('jugador1@f5gol.com'));
    }
}
