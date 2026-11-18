<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CanchaController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\JugadorAnotadoController;
use App\Http\Controllers\ReservaController;
use App\Http\Controllers\TurnoController;
use Illuminate\Support\Facades\Route;

// Máximo 10 intentos por minuto por IP: frena a quien prueba contraseñas al azar (429)
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Login con Google (Socialite): el navegador entra por redirect y Google vuelve al callback
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect']);
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);

Route::get('/user', [AuthController::class, 'me'])->middleware('auth:sanctum');

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// Nota: esta ruta tiene que declararse antes que el "show" público de canchas
// (canchas/{id}), porque si no Laravel interpreta "eliminadas" como un id.
Route::middleware('auth:sanctum')->get('canchas/eliminadas', [CanchaController::class, 'eliminadas']);

// Rutas públicas (lectura)
Route::apiResource('clubs', ClubController::class)->only(['index', 'show']);
Route::apiResource('canchas', CanchaController::class)->only(['index', 'show']);
Route::apiResource('turnos', TurnoController::class)->only(['index', 'show']);

// Rutas protegidas (escritura)
Route::middleware('auth:sanctum')->group(function () {
    // Gestión del club, sus canchas y sus turnos: solo administradores
    Route::middleware('administrador')->group(function () {
        Route::apiResource('clubs', ClubController::class)->except(['index', 'show']);
        Route::post('clubs/{id}/fotos', [ClubController::class, 'subirFoto']);
        Route::delete('clubs/{id}/fotos/{indice}', [ClubController::class, 'borrarFoto'])->whereNumber('indice');
        Route::apiResource('canchas', CanchaController::class)->except(['index', 'show']);
        Route::post('canchas/{id}/fotos', [CanchaController::class, 'subirFoto']);
        Route::put('canchas/{id}/restore', [CanchaController::class, 'restore']);
        Route::delete('canchas/{id}/fotos/{indice}', [CanchaController::class, 'borrarFoto'])->whereNumber('indice');
        Route::post('turnos/generar', [TurnoController::class, 'generar']);
        Route::apiResource('turnos', TurnoController::class)->except(['index', 'show']);
        Route::patch('turnos/{id}/bloqueo', [TurnoController::class, 'bloqueo']);
    });

    // Las reservas y los jugadores anotados tienen nombres y mails: solo con sesión,
    // y cada uno ve lo suyo (o lo de sus clubes, si es administrador)
    Route::apiResource('reservas', ReservaController::class);
    Route::apiResource('jugador-anotados', JugadorAnotadoController::class);
});
