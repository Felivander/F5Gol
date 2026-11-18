<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UsuarioSeeder::class,
            ClubSeeder::class,
            CanchaSeeder::class,
            TurnoSeeder::class,
            ReservaSeeder::class,
            JugadorAnotadoSeeder::class,
            // 8 semanas de turnos y reservas pasadas para el dashboard de Power BI
            HistorialDemoSeeder::class,
        ]);
    }
}
