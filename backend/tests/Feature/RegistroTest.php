<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistroTest extends TestCase
{
    use RefreshDatabase;

    private function datos(array $cambios = []): array
    {
        return array_merge([
            'name' => 'Juan Pérez',
            'email' => 'juan@f5gol.com',
            'password' => 'secreto123',
            'password_confirmation' => 'secreto123',
            'rol' => 'jugador',
        ], $cambios);
    }

    public function test_registra_un_jugador_y_devuelve_token(): void
    {
        $respuesta = $this->postJson('/api/register', $this->datos());

        $respuesta->assertCreated()
            ->assertJsonPath('user.name', 'Juan Pérez')
            ->assertJsonPath('user.rol', 'jugador')
            ->assertJsonStructure(['access_token', 'token_type', 'user' => ['id', 'name', 'rol']])
            ->assertJsonMissingPath('user.email');

        $usuario = User::where('email', 'juan@f5gol.com')->first();
        $this->assertNotNull($usuario);
        $this->assertTrue(Hash::check('secreto123', $usuario->password));
    }

    public function test_permite_registrarse_como_administrador(): void
    {
        $this->postJson('/api/register', $this->datos(['rol' => 'administrador']))
            ->assertCreated()
            ->assertJsonPath('user.rol', 'administrador');
    }

    public function test_el_token_devuelto_sirve_para_autenticarse(): void
    {
        $token = $this->postJson('/api/register', $this->datos())->json('access_token');

        $this->withToken($token)->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('name', 'Juan Pérez');
    }

    public function test_rechaza_un_rol_invalido(): void
    {
        $this->postJson('/api/register', $this->datos(['rol' => 'superusuario']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rol');
    }

    public function test_rechaza_un_email_ya_registrado(): void
    {
        User::factory()->create(['email' => 'juan@f5gol.com']);

        $this->postJson('/api/register', $this->datos())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_rechaza_contrasenas_que_no_coinciden(): void
    {
        $this->postJson('/api/register', $this->datos(['password_confirmation' => 'otra-cosa']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }
}
