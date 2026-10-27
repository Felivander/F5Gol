<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'API F5Gol',
    version: '1.0.0',
    description: 'API de reservas de canchas de fútbol 5: clubes, canchas, turnos, reservas y jugadores anotados.'
)]
#[OA\Server(
    url: 'http://127.0.0.1:8000',
    description: 'Servidor local'
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Token Sanctum',
    description: 'Token de autenticación generado por Laravel Sanctum (POST /api/login).'
)]
#[OA\Components(
    parameters: [
        new OA\Parameter(
            parameter: 'id',
            name: 'id',
            description: 'ID del recurso',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'integer', minimum: 1),
            example: 1
        ),
    ],
    schemas: [
        new OA\Schema(
            schema: 'ClubInput',
            properties: [
                new OA\Property(property: 'administrador_id', type: 'integer', example: 1),
                new OA\Property(property: 'nombre', type: 'string', maxLength: 150, example: 'Club Atlético Norte'),
                new OA\Property(property: 'direccion', type: 'string', maxLength: 255, nullable: true, example: 'Av. Siempre Viva 742'),
                new OA\Property(property: 'telefono', type: 'string', maxLength: 30, nullable: true, example: '1144556677'),
            ]
        ),
        new OA\Schema(
            schema: 'CanchaInput',
            properties: [
                new OA\Property(property: 'club_id', type: 'integer', example: 1),
                new OA\Property(property: 'nombre', type: 'string', maxLength: 100, example: 'Cancha 1'),
                new OA\Property(property: 'tipo', type: 'string', maxLength: 20, example: 'futbol5'),
                new OA\Property(property: 'capacidad_jugadores', type: 'integer', example: 10),
                new OA\Property(property: 'descripcion', type: 'string', nullable: true, example: 'Césped sintético, techada'),
                new OA\Property(property: 'tarifa', type: 'number', format: 'float', example: 25000),
                new OA\Property(property: 'estado', type: 'string', maxLength: 20, example: 'activa'),
                new OA\Property(
                    property: 'fotos',
                    type: 'array',
                    items: new OA\Items(type: 'string', format: 'uri'),
                    nullable: true,
                    readOnly: true,
                    description: 'URLs públicas de las fotos. Se cargan con POST /api/canchas/{id}/fotos y se borran con DELETE /api/canchas/{id}/fotos/{indice}.',
                    example: ['http://localhost:8000/storage/canchas/1/foto.jpg']
                ),
            ]
        ),
        new OA\Schema(
            schema: 'TurnoInput',
            properties: [
                new OA\Property(property: 'cancha_id', type: 'integer', example: 1),
                new OA\Property(property: 'fecha', type: 'string', format: 'date', example: '2026-09-10'),
                new OA\Property(property: 'hora_inicio', type: 'string', example: '20:00'),
                new OA\Property(property: 'hora_fin', type: 'string', example: '21:00'),
                new OA\Property(property: 'estado', type: 'string', enum: ['disponible', 'ocupado', 'bloqueado'], example: 'disponible'),
                new OA\Property(property: 'cupo_maximo', type: 'integer', example: 5),
            ]
        ),
        new OA\Schema(
            schema: 'ReservaInput',
            properties: [
                new OA\Property(property: 'turno_id', type: 'integer', example: 1),
                new OA\Property(property: 'usuario_id', type: 'integer', example: 1),
                new OA\Property(property: 'tipo', type: 'string', enum: ['completa', 'individual'], example: 'completa'),
                new OA\Property(property: 'cantidad_jugadores', type: 'integer', example: 1),
                new OA\Property(property: 'estado', type: 'string', example: 'confirmada'),
                new OA\Property(property: 'estado_pago', type: 'string', example: 'pendiente'),
            ]
        ),
        new OA\Schema(
            schema: 'JugadorAnotadoInput',
            properties: [
                new OA\Property(property: 'reserva_id', type: 'integer', example: 1),
                new OA\Property(property: 'usuario_id', type: 'integer', example: 2),
                new OA\Property(property: 'fecha_inscripcion', type: 'string', format: 'date-time', example: '2026-09-05 18:30:00'),
            ]
        ),
        new OA\Schema(
            schema: 'Usuario',
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 1),
                new OA\Property(property: 'name', type: 'string', example: 'Admin Concordia FC'),
                new OA\Property(property: 'rol', type: 'string', enum: ['administrador', 'jugador'], example: 'administrador'),
            ]
        ),
        new OA\Schema(
            schema: 'ErrorValidacion',
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'El campo nombre es obligatorio. (y 1 error más)'),
                new OA\Property(
                    property: 'errors',
                    type: 'object',
                    example: [
                        'nombre' => ['El campo nombre es obligatorio.'],
                        'administrador_id' => ['El administrador indicado no existe.'],
                    ]
                ),
            ]
        ),
        new OA\Schema(
            schema: 'Mensaje',
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Operación realizada correctamente'),
            ]
        ),
    ]
)]
class OpenApi
{
}
