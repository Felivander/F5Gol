<?php

namespace Tests\Feature;

use App\Models\Reserva;
use App\Models\Turno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreaDatosDePrueba;
use Tests\TestCase;

class FiltroReservasTest extends TestCase
{
    use CreaDatosDePrueba, RefreshDatabase;

    private function reservar(Turno $turno): Reserva
    {
        return Reserva::create([
            'turno_id' => $turno->id,
            'usuario_id' => $this->crearJugador()->id,
            'tipo' => 'completa',
            'estado' => 'confirmada',
        ]);
    }

    public function test_filtra_las_reservas_por_fecha_del_turno(): void
    {
        $cancha = $this->crearCancha();
        $hoy = $this->reservar($this->crearTurno($cancha, ['fecha' => '2026-11-10']));
        $this->reservar($this->crearTurno($cancha, ['fecha' => '2026-11-11']));

        $this->getJson('/api/reservas?fecha=2026-11-10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $hoy->id);
    }

    public function test_filtra_las_reservas_por_cancha(): void
    {
        $club = $this->crearClub();
        $cancha1 = $this->crearCancha($club);
        $cancha2 = $this->crearCancha($club);
        $deLaCancha1 = $this->reservar($this->crearTurno($cancha1));
        $this->reservar($this->crearTurno($cancha2));

        $this->getJson("/api/reservas?cancha_id={$cancha1->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $deLaCancha1->id);
    }

    public function test_combina_cancha_y_fecha(): void
    {
        $cancha = $this->crearCancha();
        $otraCancha = $this->crearCancha();
        $buscada = $this->reservar($this->crearTurno($cancha, ['fecha' => '2026-11-10']));
        $this->reservar($this->crearTurno($cancha, ['fecha' => '2026-11-12']));
        $this->reservar($this->crearTurno($otraCancha, ['fecha' => '2026-11-10']));

        $this->getJson("/api/reservas?cancha_id={$cancha->id}&fecha=2026-11-10")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $buscada->id);
    }
}
