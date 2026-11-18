<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cada 5 minutos: avisa a los jugadores de los partidos de matchmaking que empezaron sin completarse.
// Necesita `php artisan schedule:work` corriendo (lo levanta iniciar-f5gol.bat).
Schedule::command('partidos:avisar-incompletos')->everyFiveMinutes()->withoutOverlapping();
