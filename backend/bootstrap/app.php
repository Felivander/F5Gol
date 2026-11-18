<?php

use App\Http\Middleware\EsAdministrador;
use App\Http\Middleware\RechazarJsonInvalido;
use App\Models\Cancha;
use App\Models\Club;
use App\Models\JugadorAnotado;
use App\Models\Reserva;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'administrador' => EsAdministrador::class,
        ]);
        $middleware->api(append: [RechazarJsonInvalido::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Todas las respuestas de error de la API, en español y diciendo qué pasó

        // 401: distingue "no mandaste token" de "tu token no sirve"
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $request->bearerToken()
                        ? 'Tu sesión no es válida o venció. Iniciá sesión de nuevo.'
                        : 'Necesitás iniciar sesión: mandá el token en el encabezado Authorization: Bearer <token>.',
                ], 401);
            }
        });

        // 422: el mensaje general dice cuántos campos hay que corregir
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                $errores = $e->errors();
                $cantidad = count($errores);

                return response()->json([
                    'message' => $cantidad === 1
                        ? reset($errores)[0]
                        : "Hay {$cantidad} datos para corregir. El detalle de cada uno está en \"errors\".",
                    'errors' => $errores,
                ], 422);
            }
        });

        // 404: dice qué no existe y con qué id, o qué ruta no existe
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $anterior = $e->getPrevious();
            if ($anterior instanceof ModelNotFoundException) {
                // Cómo se nombra cada modelo en el mensaje
                $nombre = [
                    Club::class => 'un club',
                    Cancha::class => 'una cancha',
                    Turno::class => 'un turno',
                    Reserva::class => 'una reserva',
                    JugadorAnotado::class => 'una inscripción',
                    User::class => 'un usuario',
                ][$anterior->getModel()] ?? 'un registro';
                $ids = implode(', ', $anterior->getIds());

                return response()->json([
                    'message' => $ids !== '' ? "No existe {$nombre} con el id {$ids}." : "No existe {$nombre} con esos datos.",
                ], 404);
            }

            return response()->json([
                'message' => "La ruta {$request->method()} /{$request->path()} no existe. Revisá la documentación en /api/documentation.",
            ], 404);
        });

        // 405: qué método se usó y cuáles se aceptan
        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                $permitidos = $e->getHeaders()['Allow'] ?? '';

                return response()->json([
                    'message' => "El método {$request->method()} no está permitido en /{$request->path()}."
                        .($permitidos ? " Métodos aceptados: {$permitidos}." : ''),
                ], 405, $e->getHeaders());
            }
        });

        // 403 genérico (los controladores mandan mensajes más específicos)
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'No tenés permiso para hacer esto.',
                ], 403);
            }
        });

        // 413: archivo o pedido demasiado grande para PHP
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'El archivo es demasiado grande. Las fotos pueden pesar hasta 4 MB.',
                ], 413);
            }
        });

        // 429: demasiados intentos (login y registro tienen límite)
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*')) {
                $segundos = (int) ($e->getHeaders()['Retry-After'] ?? 60);

                return response()->json([
                    'message' => "Demasiados intentos seguidos. Esperá {$segundos} segundos y probá de nuevo.",
                ], 429, $e->getHeaders());
            }
        });

        // 500: sin modo debug, nunca se muestran detalles internos
        $exceptions->render(function (Throwable $e, Request $request) {
            $yaManejado = $e instanceof HttpExceptionInterface
                || $e instanceof ValidationException
                || $e instanceof AuthenticationException;

            if ($request->is('api/*') && ! $yaManejado && ! config('app.debug')) {
                return response()->json([
                    'message' => 'Ocurrió un error inesperado en el servidor. Probá de nuevo en un rato y, si sigue pasando, avisá al administrador.',
                ], 500);
            }
        });
    })->create();
