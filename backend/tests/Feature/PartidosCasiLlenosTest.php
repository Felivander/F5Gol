<?php

namespace Tests\Feature;

use App\Models\Reserva;
use App\Models\Turno;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PartidosCasiLlenosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PartidosCasiLlenosTest extends TestCase
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

    // Partidos que no empezaron, todavía disponibles y con un solo lugar libre
    private function casiLlenos(): Collection
    {
        return Turno::where('estado', 'disponible')
            ->whereDate('fecha', '>=', now()->toDateString())
            ->get()
            ->filter(fn (Turno $t) => $t->inicio()->isFuture() && $t->cupoActual() === $t->cupo_maximo - 1)
            ->values();
    }

    public function test_el_seed_deja_cinco_partidos_a_un_lugar_de_completarse(): void
    {
        $partidos = $this->casiLlenos();

        $this->assertCount(PartidosCasiLlenosSeeder::CANTIDAD, $partidos);
        // En canchas distintas, para que en el lobby se vean variados
        $this->assertCount(PartidosCasiLlenosSeeder::CANTIDAD, $partidos->pluck('cancha_id')->unique());
        // A cada uno le falta un lugar en uno solo de sus equipos
        foreach ($partidos as $turno) {
            $this->assertSame(1, $turno->libresEquipo('A') + $turno->libresEquipo('B'));
            $this->assertTrue($turno->inicio()->isFuture());
        }
    }

    public function test_aparecen_en_el_lobby_con_un_lugar_libre(): void
    {
        $ids = $this->casiLlenos()->pluck('id');

        $lobby = collect($this->getJson('/api/partidos-abiertos')->assertOk()->json());

        $deLaDemo = $lobby->whereIn('turno_id', $ids);
        $this->assertCount(PartidosCasiLlenosSeeder::CANTIDAD, $deLaDemo);
        $this->assertSame([1], $deLaDemo->pluck('faltan')->unique()->values()->all());
    }

    public function test_usa_los_jugadores_de_demo_y_deja_libres_las_cuentas_jugador(): void
    {
        $emails = Reserva::whereIn('turno_id', $this->casiLlenos()->pluck('id'))
            ->with('usuario:id,email')->get()->pluck('usuario.email')->unique();

        $this->assertNotEmpty($emails);
        $this->assertTrue($emails->every(fn ($email) => str_starts_with($email, 'demo') && str_ends_with($email, '@f5gol.com')));
    }

    public function test_correrlo_otra_vez_no_agrega_partidos(): void
    {
        $reservas = Reserva::count();

        $this->seed(PartidosCasiLlenosSeeder::class);

        $this->assertSame($reservas, Reserva::count());
        $this->assertCount(PartidosCasiLlenosSeeder::CANTIDAD, $this->casiLlenos());
    }

    public function test_si_uno_se_completa_el_seeder_arma_otro(): void
    {
        $turno = $this->casiLlenos()->first();
        $turno->update(['estado' => 'ocupado']);

        $this->seed(PartidosCasiLlenosSeeder::class);

        $this->assertCount(PartidosCasiLlenosSeeder::CANTIDAD, $this->casiLlenos());
    }

    public function test_al_sumarse_un_jugador_el_partido_se_completa_y_avisa_a_n8n(): void
    {
        config([
            'services.n8n.webhook_partido_completo' => self::WEBHOOK,
            'services.n8n.secreto' => 'secreto-de-prueba',
        ]);
        Http::fake([self::WEBHOOK => Http::response(['ok' => true])]);
        Notification::fake();

        $turno = $this->casiLlenos()->first();
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
