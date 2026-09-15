<?php

use App\Http\Controllers\CanchaController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\JugadorAnotadoController;
use App\Http\Controllers\ReservaController;
use App\Http\Controllers\TurnoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('clubs', ClubController::class);
Route::apiResource('canchas', CanchaController::class);
Route::apiResource('turnos', TurnoController::class);
Route::apiResource('reservas', ReservaController::class);
Route::apiResource('jugador-anotados', JugadorAnotadoController::class);
