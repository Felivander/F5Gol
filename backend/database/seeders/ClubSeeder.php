<?php

namespace Database\Seeders;

use App\Models\Club;
use App\Models\User;
use Illuminate\Database\Seeder;

class ClubSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin1 = User::where('email', 'admin1@f5gol.com')->first();
        $admin2 = User::where('email', 'admin2@f5gol.com')->first();

        Club::create([
            'administrador_id' => $admin1->id,
            'nombre' => 'Complejo Concordia FC',
            'direccion' => 'Av. Costanera 1200, Concordia',
            'telefono' => '3454111111',
        ]);

        Club::create([
            'administrador_id' => $admin2->id,
            'nombre' => 'Arena Norte',
            'direccion' => 'Ruta 22 Km 3, Concordia',
            'telefono' => '3454222222',
        ]);
    }
}
