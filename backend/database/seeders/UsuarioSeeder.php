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
        // firstOrCreate por email: si el seeder se corre de nuevo, no falla ni duplica
        User::firstOrCreate(['email' => 'admin1@f5gol.com'], [
            'name' => 'Admin Concordia FC',
            'password' => Hash::make('password'),
            'rol' => 'administrador',
            'telefono' => '3454000001',
        ]);

        User::firstOrCreate(['email' => 'admin2@f5gol.com'], [
            'name' => 'Admin Arena Norte',
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
            User::firstOrCreate(['email' => 'jugador'.($i + 1).'@f5gol.com'], [
                'name' => $nombre,
                'password' => Hash::make('password'),
                'rol' => 'jugador',
                'telefono' => '345450'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
            ]);
        }
    }
}
