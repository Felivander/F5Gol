<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\CreaDatosDePrueba;
use Tests\TestCase;

class GrillaTurnosTest extends TestCase
{
    use CreaDatosDePrueba, RefreshDatabase;

    public function test_filtra_los_turnos_por_cancha_y_fecha(): void
    {
        $club = $this->crearClub();
        $cancha = $this->crearCancha($club);
        $otraCancha = $this->crearCancha($club);
        $temprano = $this->crearTurno($cancha, ['fecha' => '2026-11-10', 'hora_inicio' => '18:00', 'hora_fin' => '19:00']);
        $tarde = $this->crearTurno($cancha, ['fecha' => '2026-11-10', 'hora_inicio' => '21:00', 'hora_fin' => '22:00']);
        $this->crearTurno($cancha, ['fecha' => '2026-11-11', 'hora_inicio' => '18:00', 'hora_fin' => '19:00']);
        $this->crearTurno($otraCancha, ['fecha' => '2026-11-10', 'hora_inicio' => '18:00', 'hora_fin' => '19:00']);

        $this->getJson("/api/turnos?cancha_id={$cancha->id}&fecha=2026-11-10")
            ->assertOk()
            ->assertJsonCount(2)
            // Ordenados por hora de inicio, para armar la grilla del día
            ->assertJsonPath('0.id', $temprano->id)
            ->assertJsonPath('1.id', $tarde->id)
            ->assertJsonPath('0.cancha.id', $cancha->id);
    }

    public function test_no_hace_falta_token_para_ver_la_grilla(): void
    {
        $turno = $this->crearTurno();

        $this->getJson("/api/turnos?cancha_id={$turno->cancha_id}")
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_devuelve_el_cupo_actual_de_cada_turno(): void
    {
        $cancha = $this->crearCancha();
        $conJugadores = $this->crearTurno($cancha, ['hora_inicio' => '18:00', 'hora_fin' => '19:00']);
        $vacio = $this->crearTurno($cancha, ['hora_inicio' => '19:00', 'hora_fin' => '20:00']);
        $this->crearReserva($conJugadores, ['tipo' => 'individual', 'cantidad_jugadores' => 2]);
        $this->crearReserva($conJugadores, ['tipo' => 'individual', 'cantidad_jugadores' => 1]);
        // Las canceladas no cuentan para el cupo
        $this->crearReserva($conJugadores, ['tipo' => 'individual', 'cantidad_jugadores' => 1, 'estado' => 'cancelada']);

        $this->getJson("/api/turnos?cancha_id={$cancha->id}&fecha={$vacio->fecha}")
            ->assertOk()
            ->assertJsonPath('0.id', $conJugadores->id)
            ->assertJsonPath('0.cupo_actual', 3)
            ->assertJsonPath('0.cupo_maximo', 5)
            ->assertJsonPath('1.id', $vacio->id)
            ->assertJsonPath('1.cupo_actual', 0);
    }

    public function test_un_matchmaking_incompleto_no_se_muestra_ocupado(): void
    {
        $turno = $this->crearTurno();

        // Se van anotando jugadores de a uno hasta quedar a uno del cupo
        foreach (range(1, 4) as $vuelta) {
            $jugador = $this->crearJugador();
            Sanctum::actingAs($jugador);
            $this->postJson('/api/reservas', [
                'turno_id' => $turno->id,
                'usuario_id' => $jugador->id,
                'tipo' => 'individual',
                'cantidad_jugadores' => 1,
            ])->assertCreated();

            $this->getJson("/api/turnos?cancha_id={$turno->cancha_id}&fecha={$turno->fecha}")
                ->assertOk()
                ->assertJsonPath('0.estado', 'disponible')
                ->assertJsonPath('0.cupo_actual', $vuelta);
        }
    }

    public function test_un_matchmaking_completo_se_muestra_ocupado(): void
    {
        $turno = $this->crearTurno();
        $jugador = $this->crearJugador();
        Sanctum::actingAs($jugador);

        $this->postJson('/api/reservas', [
            'turno_id' => $turno->id,
            'usuario_id' => $jugador->id,
            'tipo' => 'individual',
            'cantidad_jugadores' => 5,
        ])->assertCreated();

        $this->getJson("/api/turnos?cancha_id={$turno->cancha_id}&fecha={$turno->fecha}")
            ->assertOk()
            ->assertJsonPath('0.estado', 'ocupado')
            ->assertJsonPath('0.cupo_actual', 5);
    }
}
