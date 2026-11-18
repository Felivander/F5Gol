<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Avisa a n8n que se creó una cuenta, para que mande el mail de bienvenida.
 *
 * Se despacha con dispatch(...)->afterResponse(): corre cuando el usuario ya recibió
 * la respuesta del registro, así no lo hace esperar y no necesita un worker de colas.
 * Si n8n no está configurado o no responde, solo queda un aviso en el log:
 * el registro nunca falla por esto.
 */
class AvisarUsuarioCreado
{
    use Dispatchable;

    /**
     * @param  'registro'|'google'  $origen  Cómo se creó la cuenta
     */
    public function __construct(
        public User $usuario,
        public string $origen,
    ) {}

    public function handle(): void
    {
        $url = config('services.n8n.webhook_usuario_creado');

        if (! $url) {
            return;
        }

        $frontend = rtrim(config('services.frontend.url'), '/');

        try {
            Http::timeout(5)
                ->withHeaders(['X-F5GOL-Secreto' => (string) config('services.n8n.secreto')])
                ->post($url, [
                    'evento' => 'usuario.creado',
                    'origen' => $this->origen,
                    'usuario' => [
                        'id' => $this->usuario->id,
                        'nombre' => $this->usuario->name,
                        'email' => $this->usuario->email,
                        'rol' => $this->usuario->rol,
                    ],
                    'creado_en' => $this->usuario->created_at?->toIso8601String(),
                    'links' => [
                        'app' => $frontend,
                        'login' => $frontend.'/login',
                        // El administrador empieza cargando su club; el jugador, buscando canchas
                        'primer_paso' => $frontend.($this->usuario->rol === 'administrador' ? '/mis-clubes' : '/clubes'),
                    ],
                ])
                ->throw();
        } catch (Throwable $e) {
            Log::warning("No se pudo avisar a n8n la creación del usuario {$this->usuario->id}: ".$e->getMessage());
        }
    }
}
