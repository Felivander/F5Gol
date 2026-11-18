<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as UsuarioGoogle;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class LoginGoogleTest extends TestCase
{
    use RefreshDatabase;

    private const FRONT = 'http://localhost:4200';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.frontend.url' => self::FRONT]);
    }

    // Simula que Google devuelve esta cuenta, sin salir a internet
    private function googleDevuelve(array $datos): void
    {
        $cuenta = (new UsuarioGoogle)->map(array_merge([
            'id' => 'google-123',
            'name' => 'Ana Gómez',
            'email' => 'ana@gmail.com',
        ], $datos));

        $proveedor = Mockery::mock(GoogleProvider::class);
        $proveedor->shouldReceive('stateless')->andReturnSelf();
        $proveedor->shouldReceive('user')->andReturn($cuenta);

        Socialite::shouldReceive('driver')->with('google')->andReturn($proveedor);
    }

    // Saca el token del fragmento de la URL a la que se redirige al front
    private function tokenDe($respuesta): string
    {
        $destino = $respuesta->headers->get('Location');
        $this->assertStringStartsWith(self::FRONT.'/auth/google#token=', $destino);

        return urldecode(substr($destino, strlen(self::FRONT.'/auth/google#token=')));
    }

    public function test_redirect_manda_a_google(): void
    {
        $proveedor = Mockery::mock(GoogleProvider::class);
        $proveedor->shouldReceive('stateless')->andReturnSelf();
        $proveedor->shouldReceive('redirect')->andReturn(new RedirectResponse('https://accounts.google.com/o/oauth2/auth?client_id=x'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($proveedor);

        $this->get('/api/auth/google/redirect')
            ->assertRedirect('https://accounts.google.com/o/oauth2/auth?client_id=x');
    }

    public function test_crea_un_jugador_nuevo_y_devuelve_un_token_que_sirve(): void
    {
        $this->googleDevuelve([]);

        $respuesta = $this->get('/api/auth/google/callback?code=abc');
        $respuesta->assertRedirect();
        $token = $this->tokenDe($respuesta);

        $usuario = User::where('email', 'ana@gmail.com')->first();
        $this->assertNotNull($usuario);
        $this->assertSame('google-123', $usuario->google_id);
        $this->assertSame('jugador', $usuario->rol);
        $this->assertNull($usuario->password);
        $this->assertNotNull($usuario->email_verified_at);

        $this->withToken($token)->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('name', 'Ana Gómez')
            ->assertJsonPath('rol', 'jugador');
    }

    public function test_si_vuelve_a_entrar_no_duplica_el_usuario(): void
    {
        $this->googleDevuelve([]);
        $this->get('/api/auth/google/callback?code=abc');
        $this->get('/api/auth/google/callback?code=def');

        $this->assertSame(1, User::where('email', 'ana@gmail.com')->count());
    }

    public function test_vincula_una_cuenta_existente_por_email_y_conserva_el_rol(): void
    {
        $admin = User::factory()->create(['email' => 'ana@gmail.com', 'rol' => 'administrador']);
        $this->googleDevuelve([]);

        $token = $this->tokenDe($this->get('/api/auth/google/callback?code=abc'));

        $this->assertSame('google-123', $admin->fresh()->google_id);
        $this->assertSame(1, User::count());
        $this->withToken($token)->getJson('/api/user')
            ->assertJsonPath('id', $admin->id)
            ->assertJsonPath('rol', 'administrador');
    }

    public function test_si_google_falla_vuelve_al_login_con_error(): void
    {
        $proveedor = Mockery::mock(GoogleProvider::class);
        $proveedor->shouldReceive('stateless')->andReturnSelf();
        $proveedor->shouldReceive('user')->andThrow(new RuntimeException('access_denied'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($proveedor);

        $this->get('/api/auth/google/callback?error=access_denied')
            ->assertRedirect(self::FRONT.'/login?error=google');

        $this->assertSame(0, User::count());
    }

    public function test_un_usuario_de_google_no_puede_entrar_con_contrasena(): void
    {
        $this->googleDevuelve([]);
        $this->get('/api/auth/google/callback?code=abc');

        $this->postJson('/api/login', ['email' => 'ana@gmail.com', 'password' => 'cualquiera'])
            ->assertUnauthorized();
    }

    public function test_el_registro_con_google_no_expone_el_google_id(): void
    {
        $this->googleDevuelve([]);
        $token = $this->tokenDe($this->get('/api/auth/google/callback?code=abc'));

        $this->withToken($token)->getJson('/api/user')->assertJsonMissingPath('google_id');
    }
}
