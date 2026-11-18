<?php

namespace Tests\Feature;

use App\Models\Reserva;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\CreaDatosDePrueba;
use Tests\TestCase;

class BorrarUsuariosTest extends TestCase
{
    use CreaDatosDePrueba, RefreshDatabase;

    private function cuenta(string $email, string $rol = 'jugador'): User
    {
        return User::factory()->create(['email' => $email, 'rol' => $rol]);
    }

    public function test_sin_mails_ni_registrados_no_borra_nada(): void
    {
        $this->cuenta('ana@gmail.com');

        $this->artisan('usuarios:borrar')->assertFailed();

        $this->assertSame(1, User::count());
    }

    public function test_borra_una_cuenta_por_mail_con_sus_reservas_y_tokens(): void
    {
        $ana = $this->cuenta('ana@gmail.com');
        $beto = $this->cuenta('beto@gmail.com');
        $ana->createToken('auth_token');
        $this->crearReserva($this->crearTurno(), ['usuario_id' => $ana->id]);

        $this->artisan('usuarios:borrar', ['emails' => ['ANA@gmail.com'], '--force' => true])->assertSuccessful();

        $this->assertModelMissing($ana);
        $this->assertModelExists($beto);
        $this->assertSame(0, Reserva::where('usuario_id', $ana->id)->count());
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $ana->id)->count());
    }

    public function test_registrados_borra_solo_las_cuentas_que_no_son_del_seeder(): void
    {
        $seeder = $this->cuenta('jugador1@f5gol.com');
        $propia = $this->cuenta('felipe@gmail.com');
        $admin = $this->cuenta('dueno@hotmail.com', 'administrador');

        $this->artisan('usuarios:borrar', ['--registrados' => true, '--force' => true])->assertSuccessful();

        $this->assertModelExists($seeder);
        $this->assertModelMissing($propia);
        $this->assertModelMissing($admin);
    }

    public function test_sin_force_pide_confirmacion(): void
    {
        $ana = $this->cuenta('ana@gmail.com');

        $this->artisan('usuarios:borrar', ['emails' => ['ana@gmail.com']])
            ->expectsConfirmation('Se van a borrar 1 cuentas con sus clubes, reservas e inscripciones. ¿Seguís?', 'no')
            ->assertSuccessful();

        $this->assertModelExists($ana);
    }

    public function test_un_partido_completo_vuelve_a_estar_disponible_si_se_borra_un_jugador(): void
    {
        $turno = $this->crearTurno(null, ['cupo_maximo' => 2, 'estado' => 'ocupado']);
        $seeder = $this->cuenta('jugador1@f5gol.com');
        $propia = $this->cuenta('felipe@gmail.com');
        $this->crearReserva($turno, ['usuario_id' => $seeder->id, 'tipo' => 'individual', 'equipo' => 'A', 'cantidad_jugadores' => 1]);
        $this->crearReserva($turno, ['usuario_id' => $propia->id, 'tipo' => 'individual', 'equipo' => 'B', 'cantidad_jugadores' => 1]);

        $this->artisan('usuarios:borrar', ['emails' => ['felipe@gmail.com'], '--force' => true])->assertSuccessful();

        $this->assertSame('disponible', $turno->fresh()->estado);
    }
}
