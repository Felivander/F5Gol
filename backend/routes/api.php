<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CanchaController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\JugadorAnotadoController;
use App\Http\Controllers\ReservaController;
use App\Http\Controllers\TurnoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

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
    Route::apiResource('turnos', TurnoController::class)->except(['index', 'show']);
    Route::apiResource('reservas', ReservaController::class)->except(['index', 'show']);
    Route::apiResource('jugador-anotados', JugadorAnotadoController::class)->except(['index', 'show']);
});
