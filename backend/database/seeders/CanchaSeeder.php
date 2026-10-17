<?php

namespace Database\Seeders;

use App\Models\Cancha;
use App\Models\Club;
use Illuminate\Database\Seeder;

class CanchaSeeder extends Seeder
{
    /**
     * Canchas que se cargan en cada club, en orden (el primer club recibe la primera lista, etc.).
     * Si hay más clubes que listas, se repite la última.
     */
    private const CANCHAS_POR_CLUB = [
        // Complejo Concordia FC
        [
            ['Cancha 1', 'futbol5', 10, 'Césped sintético, techada, con vestuarios e iluminación LED.', 17000, 'activa'],
            ['Cancha 2', 'futbol5', 10, 'Césped sintético, techada, con vestuarios e iluminación LED.', 19000, 'activa'],
            ['Cancha 3', 'futbol5', 10, 'Césped sintético al aire libre, con iluminación LED.', 15000, 'activa'],
            ['Cancha 7 Costanera', 'futbol7', 14, 'Césped sintético de última generación, al aire libre, con tribuna chica.', 26000, 'activa'],
            ['Cancha Techada Premium', 'futbol5', 10, 'Techada y climatizada, césped profesional, vestuarios con duchas.', 23000, 'activa'],
        ],
        // Arena Norte
        [
            ['Cancha 1', 'futbol5', 10, 'Césped sintético, techada, con vestuarios e iluminación LED.', 17000, 'activa'],
            ['Cancha 2', 'futbol5', 10, 'Césped sintético, techada, con vestuarios e iluminación LED.', 19000, 'activa'],
            ['Cancha 3', 'futbol5', 10, 'Piso de goma para fútbol rápido, techada.', 14000, 'activa'],
            ['Cancha 7 Norte', 'futbol7', 14, 'Césped natural, al aire libre, con iluminación.', 24000, 'activa'],
            ['Cancha 4', 'futbol5', 10, 'En mantenimiento: cambio de césped.', 15000, 'inactiva'],
        ],
    ];

    /**
     * Run the database seeds.
     *
     * Se puede correr más de una vez: si la cancha ya existe en el club, no se duplica.
     */
    public function run(): void
    {
        $listas = self::CANCHAS_POR_CLUB;

        foreach (Club::orderBy('id')->get()->values() as $i => $club) {
            $lista = $listas[min($i, count($listas) - 1)];

            foreach ($lista as [$nombre, $tipo, $capacidad, $descripcion, $tarifa, $estado]) {
                Cancha::firstOrCreate(
                    ['club_id' => $club->id, 'nombre' => $nombre],
                    [
                        'tipo' => $tipo,
                        'capacidad_jugadores' => $capacidad,
                        'descripcion' => $descripcion,
                        'tarifa' => $tarifa,
                        'estado' => $estado,
                    ]
                );
            }
        }
    }
}
