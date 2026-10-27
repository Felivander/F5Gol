<?php

namespace Tests\Feature;

use App\Models\Reserva;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\CreaDatosDePrueba;
use Tests\TestCase;

class CancelarReservaTest extends TestCase
{
    use CreaDatosDePrueba, RefreshDatabase;

    private function reservaDe(User $jugador, Turno $turno): Reserva
    {
        $turno->update(['estado' => 'ocupado']);

        return Reserva::create([
            'turno_id' => $turno->id,
            'usuario_id' => $jugador->id,
            'tipo' => 'completa',
            'estado' => 'confirmada',
        ]);
    }

    private function turnoQueEmpiezaEn(int $minutos): Turno
    {
        $inicio = now()->addMinutes($minutos);

        return $this->crearTurno(null, [
            'fecha' => $inicio->toDateString(),
            'hora_inicio' => $inicio->format('H:i'),
            'hora_fin' => $inicio->copy()->addHour()->format('H:i'),
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Mediodía fijo, así los turnos de prueba no cruzan la medianoche
        $this->travelTo(now()->setTime(12, 0));
    }

    public function test_cancela_con_tiempo_y_libera_el_turno(): void
    {
        $jugador = $this->crearJugador();
        $turno = $this->turnoQueEmpiezaEn(3 * 60);
        $reserva = $this->reservaDe($jugador, $turno);
        Sanctum::actingAs($jugador);

        $this->deleteJson("/api/reservas/{$reserva->id}")->assertOk();

        $this->assertSame('disponible', $turno->fresh()->estado);
    }

    public function test_no_deja_cancelar_a_menos_de_dos_horas(): void
    {
        $jugador = $this->crearJugador();
        $turno = $this->turnoQueEmpiezaEn(90);
        $reserva = $this->reservaDe($jugador, $turno);
        Sanctum::actingAs($jugador);

        $this->deleteJson("/api/reservas/{$reserva->id}")
            ->assertConflict()
            ->assertJsonPath('message', 'Las reservas se pueden cancelar hasta 2 horas antes del turno.');

        $this->assertDatabaseHas('reservas', ['id' => $reserva->id]);
        $this->assertSame('ocupado', $turno->fresh()->estado);
    }

    public function test_justo_en_el_limite_todavia_se_puede_cancelar(): void
    {
        $jugador = $this->crearJugador();
        $reserva = $this->reservaDe($jugador, $this->turnoQueEmpiezaEn(120));
        Sanctum::actingAs($jugador);

        $this->deleteJson("/api/reservas/{$reserva->id}")->assertOk();
    }

    public function test_el_administrador_puede_cancelar_fuera_de_plazo(): void
    {
        $reserva = $this->reservaDe($this->crearJugador(), $this->turnoQueEmpiezaEn(30));
        Sanctum::actingAs($this->crearAdministrador());

        $this->deleteJson("/api/reservas/{$reserva->id}")->assertOk();
    }
}
