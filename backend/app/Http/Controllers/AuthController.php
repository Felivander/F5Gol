<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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
            'name.required' => 'El campo nombre es obligatorio.',
            'email.required' => 'El campo correo electrónico es obligatorio.',
            'email.email' => 'El campo correo electrónico debe ser una dirección de correo válida.',
            'email.unique' => 'Ya existe una cuenta con ese correo electrónico.',
            'password.required' => 'El campo contraseña es obligatorio.',
            'password.min' => 'La contraseña tiene que tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'rol.required' => 'Elegí si te registrás como jugador o como administrador.',
            'rol.in' => 'El rol tiene que ser jugador o administrador.',
        ]);

        // El cast "hashed" del modelo encripta la contraseña al guardarla
        $user = User::create($datos);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Usuario registrado correctamente',
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
            new OA\Response(response: 401, description: 'Credenciales incorrectas.'),
            new OA\Response(response: 422, description: 'Datos inválidos.'),
        ]
    )]
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ],
        [
            'email.required' => 'El campo correo electrónico es obligatorio.',
            'email.email' => 'El campo correo electrónico debe ser una dirección de correo válida.',
            'password.required' => 'El campo contraseña es obligatorio.',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Credenciales incorrectas'
            ], 401);
        }

        // Generación del token Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login exitoso',
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
            'message' => 'Sesión cerrada correctamente'
        ]);
    }
}
