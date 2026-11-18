<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Login con Google (Laravel Socialite). Las credenciales se crean en Google Cloud Console
    // y la URL de retorno tiene que coincidir exactamente con la registrada allá.
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', rtrim(env('APP_URL', 'http://localhost:8000'), '/').'/api/auth/google/callback'),
    ],

    // Automatización con n8n: Laravel avisa a este webhook cada vez que se crea una cuenta.
    // El secreto viaja en el encabezado X-F5GOL-Secreto y el flujo lo verifica.
    'n8n' => [
        'webhook_usuario_creado' => env('N8N_WEBHOOK_USUARIO_CREADO'),
        'webhook_partido_completo' => env('N8N_WEBHOOK_PARTIDO_COMPLETO'),
        'webhook_partido_incompleto' => env('N8N_WEBHOOK_PARTIDO_INCOMPLETO'),
        'secreto' => env('N8N_WEBHOOK_SECRETO'),
    ],

    // Dirección del front de Angular, a donde vuelve el usuario después de Google
    'frontend' => [
        'url' => env('FRONTEND_URL', 'http://localhost:4200'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
