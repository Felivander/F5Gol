<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deja pasar solo a usuarios con rol "administrador".
 *
 * Va después de auth:sanctum, así que acá el usuario ya está autenticado:
 * un jugador logueado recibe 403, no 401.
 */
class EsAdministrador
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->rol !== 'administrador') {
            return response()->json([
                'message' => 'Solo un administrador puede realizar esta acción.',
            ], 403);
        }

        return $next($request);
    }
}
