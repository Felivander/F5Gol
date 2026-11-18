<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\AvisarUsuarioCreado;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    // Datos del usuario que se devuelven en las respuestas; el resto (email, fechas, etc.) no hace falta
    private const CAMPOS_USUARIO = ['id', 'name', 'rol'];

    #[OA\Post(
        path: '/api/register',
        summary: 'Registrar usuario',
        description: 'Crea la cuenta eligiendo el rol (jugador o administrador) y devuelve un access_token, igual que el login.',
        tags: ['Autenticación'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'password_confirmation', 'rol'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Juan Pérez'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'juan@f5gol.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'secreto123'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'secreto123'),
                    new OA\Property(property: 'rol', type: 'string', enum: ['jugador', 'administrador'], example: 'jugador'),
                    new OA\Property(property: 'telefono', type: 'string', nullable: true, example: '1155554444'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Usuario registrado.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'Usuario registrado correctamente'),
                    new OA\Property(property: 'access_token', type: 'string', example: '1|xxxxxxxxxxxxxxxxxxxxxxxx'),
                    new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                    new OA\Property(property: 'user', ref: '#/components/schemas/Usuario'),
                ])
            ),
            new OA\Response(response: 422, description: 'Datos inválidos: devuelve un mensaje por cada campo con error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
            new OA\Response(response: 429, description: 'Demasiados intentos: máximo 10 por minuto.'),
        ]
    )]
    public function register(Request $request)
    {
        $datos = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'rol' => 'required|in:jugador,administrador',
            'telefono' => 'nullable|string|max:30',
        ],
        [
            'name.required' => 'Falta tu nombre.',
            'email.required' => 'Falta tu correo electrónico.',
            'email.email' => 'El correo electrónico no es válido: tiene que ser como nombre@dominio.com.',
            'email.unique' => 'Ya existe una cuenta con ese correo electrónico. Iniciá sesión o usá otro correo.',
            'password.required' => 'Falta la contraseña.',
            'password.min' => 'La contraseña tiene que tener al menos 8 caracteres.',
            'password.confirmed' => 'Las dos contraseñas no coinciden.',
            'rol.required' => 'Elegí si te registrás como jugador o como administrador.',
            'rol.in' => 'El rol tiene que ser jugador o administrador.',
        ]);

        // El cast "hashed" del modelo encripta la contraseña al guardarla
        $user = User::create($datos);

        // n8n manda el mail de bienvenida (corre después de responder; si falla, no afecta el registro)
        AvisarUsuarioCreado::dispatch($user, 'registro')->afterResponse();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => "¡Bienvenido a F5GOL, {$user->name}! Tu cuenta se creó correctamente.",
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->only(self::CAMPOS_USUARIO)
        ], 201);
    }

    #[OA\Post(
        path: '/api/login',
        summary: 'Iniciar sesión',
        description: 'Devuelve un access_token de Sanctum para usar con el botón Authorize.',
        tags: ['Autenticación'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin1@f5gol.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login exitoso.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'Login exitoso'),
                    new OA\Property(property: 'access_token', type: 'string', example: '1|xxxxxxxxxxxxxxxxxxxxxxxx'),
                    new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                    new OA\Property(property: 'user', ref: '#/components/schemas/Usuario'),
                ])
            ),
            new OA\Response(response: 401, description: 'El correo o la contraseña son incorrectos.'),
            new OA\Response(response: 422, description: 'Faltan el correo o la contraseña, o el correo no tiene un formato válido.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
            new OA\Response(response: 429, description: 'Demasiados intentos: máximo 10 por minuto.'),
        ]
    )]
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ],
        [
            'email.required' => 'Falta tu correo electrónico.',
            'email.email' => 'El correo electrónico no es válido: tiene que ser como nombre@dominio.com.',
            'password.required' => 'Falta la contraseña.',
        ]);

        $user = User::where('email', $request->email)->first();

        // Quien se registró con Google no tiene contraseña: solo puede entrar con Google
        if (!$user || !$user->password || !Hash::check($request->password, $user->password)) {
            return response()->json([
                // Mismo mensaje exista o no la cuenta, para no revelar qué mails están registrados
                'message' => 'El correo o la contraseña son incorrectos. Si te registraste con Google, usá el botón "Ingresar con Google".'
            ], 401);
        }

        // Generación del token Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => "Hola de nuevo, {$user->name}.",
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->only(self::CAMPOS_USUARIO)
        ]);
    }

    #[OA\Get(
        path: '/api/user',
        summary: 'Obtener usuario autenticado',
        tags: ['Autenticación'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Usuario autenticado correctamente.',
                content: new OA\JsonContent(ref: '#/components/schemas/Usuario')
            ),
            new OA\Response(response: 401, description: 'No autenticado.'),
        ]
    )]
    public function me(Request $request)
    {
        return response()->json($request->user()->only(self::CAMPOS_USUARIO));
    }

    #[OA\Post(
        path: '/api/logout',
        summary: 'Cerrar sesión',
        description: 'Revoca el token con el que se hizo el pedido. Después de esto, ese token deja de servir.',
        tags: ['Autenticación'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sesión cerrada.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'Sesión cerrada correctamente'),
                ])
            ),
            new OA\Response(response: 401, description: 'No autenticado.'),
        ]
    )]
    public function logout(Request $request)
    {
        // Borra solo el token actual: las sesiones en otros dispositivos siguen activas
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Cerraste sesión. El token ya no sirve.'
        ]);
    }
}
