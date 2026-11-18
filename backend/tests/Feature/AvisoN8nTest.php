<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as UsuarioGoogle;
use Mockery;
use Tests\TestCase;

class AvisoN8nTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK = 'http://n8n.test/webhook/f5gol-usuario-creado';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.n8n.webhook_usuario_creado' => self::WEBHOOK,
            'services.n8n.secreto' => 'secreto-de-prueba',
            'services.frontend.url' => 'http://localhost:4200',
        ]);
    }

    private function registrar(string $rol = 'jugador')
    {
        return $this->postJson('/api/register', [
            'name' => 'Ana Gómez',
            'email' => 'ana@f5gol.com',
            'password' => 'secreto123',
            'password_confirmation' => 'secreto123',
            'rol' => $rol,
        ]);
    }

    private function googleDevuelve(string $email): void
    {
        $cuenta = (new UsuarioGoogle)->map(['id' => 'google-1', 'name' => 'Ana Gómez', 'email' => $email]);
        $proveedor = Mockery::mock(GoogleProvider::class);
        $proveedor->shouldReceive('stateless')->andReturnSelf();
        $proveedor->shouldReceive('user')->andReturn($cuenta);
        Socialite::shouldReceive('driver')->with('google')->andReturn($proveedor);
    }

    public function test_al_registrarse_avisa_a_n8n_con_los_datos_del_usuario(): void
    {
        Http::fake([self::WEBHOOK => Http::response(['ok' => true])]);

        $this->registrar('administrador')->assertCreated();

        Http::assertSent(function (Request $pedido) {
            return $pedido->url() === self::WEBHOOK
                && $pedido->method() === 'POST'
                && $pedido->hasHeader('X-F5GOL-Secreto', 'secreto-de-prueba')
                && $pedido['evento'] === 'usuario.creado'
                && $pedido['origen'] === 'registro'
                && $pedido['usuario']['email'] === 'ana@f5gol.com'
                && $pedido['usuario']['rol'] === 'administrador'
                && $pedido['links']['primer_paso'] === 'http://localhost:4200/mis-clubes'
                // Nunca se manda la contraseña
                && ! isset($pedido['usuario']['password']);
        });
    }

    public function test_si_n8n_no_responde_el_registro_funciona_igual(): void
    {
        Http::fake(fn () => throw new ConnectionException('n8n apagado'));

        $this->registrar()->assertCreated()->assertJsonStructure(['access_token']);

        $this->assertNotNull(User::where('email', 'ana@f5gol.com')->first());
    }

    public function test_si_n8n_responde_con_error_el_registro_funciona_igual(): void
    {
        Http::fake([self::WEBHOOK => Http::response(['message' => 'secreto inválido'], 401)]);

        $this->registrar()->assertCreated();
    }

    public function test_sin_webhook_configurado_no_avisa(): void
    {
        config(['services.n8n.webhook_usuario_creado' => null]);
        Http::fake();

        $this->registrar()->assertCreated();

        Http::assertNothingSent();
    }

    public function test_un_registro_invalido_no_avisa(): void
    {
        Http::fake();

        $this->postJson('/api/register', ['email' => 'no-es-un-mail'])->assertUnprocessable();

        Http::assertNothingSent();
    }

    public function test_la_primera_vez_con_google_avisa_con_origen_google(): void
    {
        Http::fake([self::WEBHOOK => Http::response(['ok' => true])]);
        $this->googleDevuelve('nueva@gmail.com');

        $this->get('/api/auth/google/callback?code=abc')->assertRedirect();

        Http::assertSent(fn (Request $pedido) => $pedido['origen'] === 'google'
            && $pedido['usuario']['email'] === 'nueva@gmail.com'
            && $pedido['usuario']['rol'] === 'jugador');
    }

    public function test_si_ya_tenia_cuenta_y_entra_con_google_no_vuelve_a_avisar(): void
    {
        User::factory()->create(['email' => 'vieja@gmail.com']);
        Http::fake();
        $this->googleDevuelve('vieja@gmail.com');

        $this->get('/api/auth/google/callback?code=abc')->assertRedirect();

        Http::assertNothingSent();
    }
}
