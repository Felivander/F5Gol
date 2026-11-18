<?php

namespace Tests\Feature;

use App\Models\Turno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\CreaDatosDePrueba;
use Tests\TestCase;

class GenerarTurnosTest extends TestCase
{
    use CreaDatosDePrueba, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // Lunes 7/12/2026 a las 8 de la mañana: todos los turnos del día todavía no empezaron
    private function congelarReloj(): void
    {
        Carbon::setTestNow('2026-12-07 08:00:00');
    }

    private function datos(int $canchaId, array $cambios = []): array
    {
        return array_merge([
            'cancha_id' => $canchaId,
            'desde' => '2026-12-07',
            'hasta' => '2026-12-13',
            'hora_desde' => '18:00',
            'hora_hasta' => '22:00',
            'duracion' => 60,
        ], $cambios);
    }

    public function test_genera_los_turnos_de_toda_la_semana(): void
    {
        $this->congelarReloj();
        $admin = $this->crearAdministrador();
        $cancha = $this->crearCancha($this->crearClub($admin));

        // 7 días x 4 turnos (18, 19, 20 y 21 hs)
        $this->actingAs($admin)->postJson('/api/turnos/generar', $this->datos($cancha->id))
            ->assertCreated()
            ->assertJsonPath('creados', 28)
            ->assertJsonPath('omitidos', 0);

        $turno = Turno::where('cancha_id', $cancha->id)->orderBy('fecha')->orderBy('hora_inicio')->first();
        $this->assertSame('18:00', substr($turno->hora_inicio, 0, 5));
        $this->assertSame('19:00', substr($turno->hora_fin, 0, 5));
        $this->assertSame('disponible', $turno->estado);
        // La mitad de la capacidad de la cancha (10 jugadores)
        $this->assertSame(5, $turno->cupo_maximo);
    }

    public function test_respeta_los_dias_elegidos_y_la_duracion(): void
    {
        $this->congelarReloj();
        $admin = $this->crearAdministrador();
        $cancha = $this->crearCancha($this->crearClub($admin));

        // Solo sábado (6) y domingo (0), turnos de 90 minutos entre 18 y 22: 18:00 y 19:30 (el de 21:00 terminaría 22:30)
        $this->actingAs($admin)->postJson('/api/turnos/generar', $this->datos($cancha->id, [
            'dias' => [6, 0],
            'duracion' => 90,
        ]))->assertCreated()->assertJsonPath('creados', 4);

        $fechas = Turno::where('cancha_id', $cancha->id)->pluck('fecha')->map(fn ($f) => substr($f, 0, 10))->unique()->sort()->values()->all();
        $this->assertSame(['2026-12-12', '2026-12-13'], $fechas);
        $this->assertSame(['18:00', '19:30'], Turno::where('fecha', '2026-12-12')->orderBy('hora_inicio')->pluck('hora_inicio')->map(fn ($h) => substr($h, 0, 5))->all());
    }

    public function test_no_duplica_ni_superpone_turnos_existentes(): void
    {
        $this->congelarReloj();
        $admin = $this->crearAdministrador();
        $cancha = $this->crearCancha($this->crearClub($admin));
        // Ya existe un turno de 20:30 a 21:30 el lunes
        $this->crearTurno($cancha, ['fecha' => '2026-12-07', 'hora_inicio' => '20:30', 'hora_fin' => '21:30']);

        // El lunes se saltean 20:00 y 21:00 (se superponen): se crean 18:00 y 19:00
        $this->actingAs($admin)->postJson('/api/turnos/generar', $this->datos($cancha->id, ['hasta' => '2026-12-07']))
            ->assertCreated()
            ->assertJsonPath('creados', 2)
            ->assertJsonPath('omitidos', 2);

        // Si se vuelve a generar, no crea nada nuevo
        $this->actingAs($admin)->postJson('/api/turnos/generar', $this->datos($cancha->id, ['hasta' => '2026-12-07']))
            ->assertJsonPath('creados', 0);
    }

    public function test_no_crea_turnos_que_ya_empezaron(): void
    {
        Carbon::setTestNow('2026-12-07 19:30:00');
        $admin = $this->crearAdministrador();
        $cancha = $this->crearCancha($this->crearClub($admin));

        // Hoy ya empezaron los de 18 y 19: se crean 20 y 21
        $this->actingAs($admin)->postJson('/api/turnos/generar', $this->datos($cancha->id, ['hasta' => '2026-12-07']))
            ->assertJsonPath('creados', 2)
            ->assertJsonPath('omitidos', 2);
    }

    public function test_valida_fechas_horas_y_rango(): void
    {
        $this->congelarReloj();
        $admin = $this->crearAdministrador();
        $cancha = $this->crearCancha($this->crearClub($admin));

        $this->actingAs($admin)->postJson('/api/turnos/generar', $this->datos($cancha->id, [
            'desde' => '2026-12-01',      // antes de hoy
            'hora_hasta' => '17:00',      // antes de la hora de inicio
            'duracion' => 45,             // no permitida
            'dias' => [],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['desde', 'hora_hasta', 'duracion', 'dias']);

        $this->actingAs($admin)->postJson('/api/turnos/generar', $this->datos($cancha->id, [
            'hasta' => '2027-04-01',      // más de 90 días
        ]))->assertUnprocessable()->assertJsonValidationErrors('hasta');
    }

    public function test_otro_administrador_no_puede_generar_turnos_en_una_cancha_ajena(): void
    {
        $this->congelarReloj();
        $cancha = $this->crearCancha();

        $this->actingAs($this->crearAdministrador())
            ->postJson('/api/turnos/generar', $this->datos($cancha->id))
            ->assertForbidden();

        $this->assertSame(0, Turno::count());
    }

    public function test_un_jugador_no_puede_generar_turnos(): void
    {
        $this->congelarReloj();
        $cancha = $this->crearCancha();

        $this->actingAs($this->crearJugador())
            ->postJson('/api/turnos/generar', $this->datos($cancha->id))
            ->assertForbidden();
    }

    public function test_otro_administrador_no_puede_crear_editar_ni_borrar_turnos_ajenos(): void
    {
        $turno = $this->crearTurno();
        $intruso = $this->crearAdministrador();

        $this->actingAs($intruso)->postJson('/api/turnos', [
            'cancha_id' => $turno->cancha_id,
            'fecha' => now()->addDays(3)->toDateString(),
            'hora_inicio' => '10:00',
            'hora_fin' => '11:00',
        ])->assertForbidden();
        $this->actingAs($intruso)->putJson("/api/turnos/{$turno->id}", ['hora_fin' => '22:00'])->assertForbidden();
        $this->actingAs($intruso)->deleteJson("/api/turnos/{$turno->id}")->assertForbidden();

        $this->assertNotNull($turno->fresh());
    }

    public function test_el_dueno_no_puede_borrar_un_turno_con_reservas_activas(): void
    {
        $admin = $this->crearAdministrador();
        $turno = $this->crearTurno($this->crearCancha($this->crearClub($admin)));
        $this->crearReserva($turno);

        $this->actingAs($admin)->deleteJson("/api/turnos/{$turno->id}")->assertStatus(409);
        $this->assertNotNull($turno->fresh());
    }

    public function test_el_dueno_borra_un_turno_libre(): void
    {
        $admin = $this->crearAdministrador();
        $turno = $this->crearTurno($this->crearCancha($this->crearClub($admin)));

        $this->actingAs($admin)->deleteJson("/api/turnos/{$turno->id}")->assertOk();
        $this->assertNull($turno->fresh());
    }
}
