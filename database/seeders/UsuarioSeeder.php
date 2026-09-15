<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsuarioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Admin Concordia FC',
            'email' => 'admin1@f5gol.com',
            'password' => Hash::make('password'),
            'rol' => 'administrador',
            'telefono' => '3454000001',
        ]);

        User::create([
            'name' => 'Admin Arena Norte',
            'email' => 'admin2@f5gol.com',
            'password' => Hash::make('password'),
            'rol' => 'administrador',
            'telefono' => '3454000002',
        ]);

        $jugadores = [
            'Lautaro Meza',
            'Patricio Sandri',
            'Felipe van der Donckt',
            'Julián Dufey',
            'Martín Gómez',
            'Nicolás Ferreyra',
            'Agustín Rolón',
            'Bruno Acosta',
        ];

        foreach ($jugadores as $i => $nombre) {
            User::create([
                'name' => $nombre,
                'email' => 'jugador'.($i + 1).'@f5gol.com',
                'password' => Hash::make('password'),
                'rol' => 'jugador',
                'telefono' => '345450'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
            ]);
        }
    }
}
