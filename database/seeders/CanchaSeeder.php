<?php

namespace Database\Seeders;

use App\Models\Cancha;
use App\Models\Club;
use Illuminate\Database\Seeder;

class CanchaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clubs = Club::all();

        foreach ($clubs as $club) {
            for ($i = 1; $i <= 2; $i++) {
                Cancha::create([
                    'club_id' => $club->id,
                    'nombre' => "Cancha $i",
                    'tipo' => 'futbol5',
                    'capacidad_jugadores' => 10,
                    'descripcion' => 'Césped sintético, techada, con vestuarios e iluminación LED.',
                    'tarifa' => 15000 + ($i * 2000),
                    'estado' => 'activa',
                ]);
            }
        }
    }
}
