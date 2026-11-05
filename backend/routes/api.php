<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CanchaController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\JugadorAnotadoController;
use App\Http\Controllers\ReservaController;
use App\Http\Controllers\TurnoController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::get('/user', [AuthController::class, 'me'])->middleware('auth:sanctum');

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// Nota: esta ruta tiene que declararse antes que el "show" público de canchas
// (canchas/{id}), porque si no Laravel interpreta "eliminadas" como un id.
Route::middleware('auth:sanctum')->get('canchas/eliminadas', [CanchaController::class, 'eliminadas']);

// Rutas públicas (lectura)
Route::apiResource('clubs', ClubController::class)->only(['index', 'show']);
Route::apiResource('canchas', CanchaController::class)->only(['index', 'show']);
Route::apiResource('turnos', TurnoController::class)->only(['index', 'show']);
Route::apiResource('reservas', ReservaController::class)->only(['index', 'show']);
Route::apiResource('jugador-anotados', JugadorAnotadoController::class)->only(['index', 'show']);

// Rutas protegidas (escritura)
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('clubs', ClubController::class)->except(['index', 'show']);
    Route::apiResource('canchas', CanchaController::class)->except(['index', 'show']);
    Route::put('canchas/{id}/restore', [CanchaController::class, 'restore']);
    Route::apiResource('turnos', TurnoController::class)->except(['index', 'show']);
    Route::apiResource('reservas', ReservaController::class)->except(['index', 'show']);
    Route::apiResource('jugador-anotados', JugadorAnotadoController::class)->except(['index', 'show']);
});
