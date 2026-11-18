<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeedersTest extends TestCase
{
    use RefreshDatabase;

    private function contar(): array
    {
        return collect(['users', 'clubs', 'canchas', 'turnos', 'reservas', 'jugador_anotados'])
            ->mapWithKeys(fn ($tabla) => [$tabla => DB::table($tabla)->count()])
            ->all();
    }

    public function test_el_seed_carga_cada_club_una_sola_vez(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(['Arena Norte', 'Complejo Concordia FC'], Club::orderBy('nombre')->pluck('nombre')->all());
    }

    public function test_correr_el_seed_dos_veces_no_duplica_nada(): void
    {
        $this->seed(DatabaseSeeder::class);
        $antes = $this->contar();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($antes, $this->contar());
    }

    public function test_el_seed_no_carga_reservas_a_cuentas_reales(): void
    {
        $real = User::factory()->create(['email' => 'alguien@gmail.com', 'rol' => 'jugador']);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, DB::table('reservas')->where('usuario_id', $real->id)->count());
    }
}
