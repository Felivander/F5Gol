<?php

namespace App\Http\Controllers;

use App\Jobs\AvisarUsuarioCreado;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use OpenApi\Attributes as OA;
use Throwable;

/**
 * Login con Google (OAuth 2.0) usando Laravel Socialite.
 *
 * Flujo:
 * 1. El front manda al navegador a /api/auth/google/redirect.
 * 2. Laravel lo redirige a la pantalla de Google.
 * 3. Google vuelve a /api/auth/google/callback con un código.
 * 4. Laravel obtiene los datos de la cuenta, busca o crea el usuario y genera un token de Sanctum.
 * 5. Laravel redirige al front a /auth/google#token=..., que guarda el token igual que en el login normal.
 *
 * Se usa stateless() porque la API no tiene sesión: la seguridad del resto de la app sigue
 * siendo el token de Sanctum. El token viaja en el fragmento (#) de la URL para que no
 * quede en los logs del servidor del front.
 */
class GoogleAuthController extends Controller
{
    #[OA\Get(
        path: '/api/auth/google/redirect',
        summary: 'Ingresar con Google',
        description: 'Redirige el navegador a la pantalla de inicio de sesión de Google. Se abre desde el navegador (no con fetch/XHR): Swagger UI no puede seguir la redirección.',
        tags: ['Autenticación'],
        responses: [
            new OA\Response(response: 302, description: 'Redirección a accounts.google.com.'),
        ]
    )]
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    #[OA\Get(
        path: '/api/auth/google/callback',
        summary: 'Retorno de Google',
        description: 'Google llama a esta URL después de que el usuario elige su cuenta. Busca el usuario por su cuenta de Google o por su email (si ya estaba registrado con contraseña, vincula la cuenta); si no existe, lo crea como jugador. Después redirige al front con el token de Sanctum en el fragmento: FRONTEND_URL/auth/google#token=... Si algo falla, redirige a FRONTEND_URL/login?error=google.',
        tags: ['Autenticación'],
        parameters: [
            new OA\Parameter(name: 'code', description: 'Código de autorización que manda Google', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'error', description: 'Lo manda Google si el usuario canceló', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'access_denied')),
        ],
        responses: [
            new OA\Response(response: 302, description: 'Redirección al front: /auth/google#token=... si salió bien, /login?error=google si no.'),
        ]
    )]
    public function callback(): RedirectResponse
    {
        $frontend = rtrim(config('services.frontend.url'), '/');

        try {
            $cuentaGoogle = Socialite::driver('google')->stateless()->user();
        } catch (Throwable $e) {
            // El usuario canceló, el código venció o las credenciales de Google están mal configuradas
            Log::warning('Falló el login con Google: '.$e->getMessage());

            return redirect()->away($frontend.'/login?error=google');
        }

        if (! $cuentaGoogle->getEmail()) {
            return redirect()->away($frontend.'/login?error=google');
        }

        $usuario = $this->buscarOCrearUsuario(
            $cuentaGoogle->getId(),
            $cuentaGoogle->getEmail(),
            $cuentaGoogle->getName() ?: $cuentaGoogle->getEmail()
        );

        $token = $usuario->createToken('google_token')->plainTextToken;

        return redirect()->away($frontend.'/auth/google#token='.urlencode($token));
    }

    private function buscarOCrearUsuario(string $googleId, string $email, string $nombre): User
    {
        // 1. Ya entró antes con esta cuenta de Google
        $usuario = User::where('google_id', $googleId)->first();
        if ($usuario) {
            return $usuario;
        }

        // 2. Ya tenía cuenta con ese email (registrada con contraseña): se vincula a Google
        //    y conserva su rol, así un administrador también puede entrar con Google
        $usuario = User::where('email', $email)->first();
        if ($usuario) {
            $usuario->google_id = $googleId;
            $usuario->email_verified_at ??= now();
            $usuario->save();

            return $usuario;
        }

        // 3. Usuario nuevo: el rol lo decide el servidor y siempre es jugador
        $usuario = new User([
            'name' => $nombre,
            'email' => $email,
            'rol' => 'jugador',
        ]);
        $usuario->google_id = $googleId;
        $usuario->email_verified_at = now();
        $usuario->save();

        // Cuenta nueva: n8n manda el mail de bienvenida (igual que en el registro normal)
        AvisarUsuarioCreado::dispatch($usuario, 'google')->afterResponse();

        return $usuario;
    }
}
