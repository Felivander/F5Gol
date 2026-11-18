<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Si el cuerpo dice ser JSON pero está mal escrito, Laravel lo toma como vacío
 * y respondería "falta tal campo", que confunde. Acá se corta antes con un 400 claro.
 */
class RechazarJsonInvalido
{
    public function handle(Request $request, Closure $next): Response
    {
        $cuerpo = $request->getContent();

        if ($request->isJson() && trim($cuerpo) !== '') {
            json_decode($cuerpo);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'message' => 'El cuerpo del pedido no es un JSON válido. Revisá comillas, comas y llaves.',
                ], 400);
            }
        }

        return $next($request);
    }
}
