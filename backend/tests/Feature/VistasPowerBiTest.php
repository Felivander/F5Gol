<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\HistorialDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\CreaDatosDePrueba;
use Tests\TestCase;

class VistasPowerBiTest extends TestCase
{
    use CreaDatosDePrueba, RefreshDatabase;

    public function test_bi_reservas_calcula_el_monto_de_cada_tipo_de_reserva(): void
    {
        $cancha = $this->crearCancha(); // tarifa 20000
        $turno = $this->crearTurno($cancha, ['cupo_maximo' => 5]);
        $completa = $this->crearReserva($turno, ['tipo' => 'completa', 'estado_pago' => 'pagado']);
        $individual = $this->crearReserva($this->crearTurno($cancha, ['hora_inicio' => '21:00', 'hora_fin' => '22:00', 'cupo_maximo' => 5]), [
            'tipo' => 'individual',
            'cantidad_jugadores' => 2,
        ]);

        $filas = DB::table('bi_reservas')->get()->keyBy('reserva_id');

        $this->assertEquals(20000, $filas[$completa->id]->monto);
        // 2 de 5 jugadores: 2/5 de la tarifa
        $this->assertEquals(8000, $filas[$individual->id]->monto);
        $this->assertSame('Club de prueba', $filas[$completa->id]->club);
        $this->assertSame('pagado', $filas[$completa->id]->estado_pago);
    }

    public function test_bi_usuarios_no_expone_datos_personales_y_marca_el_origen(): void
    {
        $conGoogle = User::factory()->create();
        $conGoogle->forceFill(['google_id' => 'g-1'])->save();
        User::factory()->create();

        $fila = (array) DB::table('bi_usuarios')->where('usuario_id', $conGoogle->id)->first();

        $this->assertSame('Google', $fila['origen']);
        $this->assertArrayNotHasKey('email', $fila);
        $this->assertArrayNotHasKey('name', $fila);
        $this->assertSame(1, DB::table('bi_usuarios')->where('origen', 'Formulario')->count());
    }

    public function test_bi_turnos_trae_cancha_y_club(): void
    {
        $turno = $this->crearTurno();

        $fila = DB::table('bi_turnos')->where('turno_id', $turno->id)->first();

        $this->assertSame('Cancha 1', $fila->cancha);
        $this->assertSame('Club de prueba', $fila->club);
        $this->assertEquals(0, $fila->cancha_eliminada);
    }

    public function test_el_historial_de_demo_carga_semanas_pasadas_y_no_se_duplica(): void
    {
        $this->crearCancha();

        $this->seed(HistorialDemoSeeder::class);
        $turnos = DB::table('turnos')->count();
        $reservas = DB::table('reservas')->count();

        // 8 semanas x 8 horarios por día en la cancha activa
        $this->assertSame(8 * 7 * 8, $turnos);
        $this->assertGreaterThan(100, $reservas);
        $this->assertSame(0, DB::table('turnos')->where('fecha', '>=', now()->toDateString())->count());
        $this->assertSame(30, User::where('email', 'like', 'demo%@f5gol.com')->count());

        $this->seed(HistorialDemoSeeder::class);
        $this->assertSame($turnos, DB::table('turnos')->count());
    }

    public function test_el_historial_de_demo_no_carga_reservas_a_cuentas_reales(): void
    {
        $this->crearCancha();
        $real = User::factory()->create(['email' => 'alguien@gmail.com', 'rol' => 'jugador']);

        $this->seed(HistorialDemoSeeder::class);

        $this->assertSame(0, DB::table('reservas')->where('usuario_id', $real->id)->count());
    }
}
