# F5Gol

Sistema de reservas de canchas de fútbol 5: clubes, canchas, turnos, reservas y jugadores anotados.
Proyecto de Programación 4 (UTN Facultad Regional Concordia).

La idea completa del proyecto está en [idea_de_proyecto_F5GOL.md](idea_de_proyecto_F5GOL.md).

## Estructura

| Carpeta | Qué es | Puerto |
|---|---|---|
| [`backend/`](backend) | API REST en Laravel 13 + Sanctum, documentada con Swagger | 8000 |
| [`frontend/`](frontend) | Aplicación Angular 22 que consume la API | 4200 |

En desarrollo, el front redirige todo lo que va a `/api` hacia el back (ver `frontend/proxy.conf.json`), así que no hace falta configurar CORS.

## Requisitos

- XAMPP con MySQL
- PHP 8.3 y Composer
- Node.js 24 y Angular CLI 22 (`npm install -g @angular/cli@22`)

## Primera vez (después de clonar)

Back:

```bash
cd backend
composer install
copy .env.example .env
php artisan key:generate
```

En `backend/.env` configurar la base de datos (`DB_DATABASE=f5gol`, usuario `root` sin contraseña en XAMPP), crear la base `f5gol` en phpMyAdmin y después:

```bash
php artisan migrate --seed
```

Front:

```bash
cd frontend
npm install
```

## Levantar todo

Doble clic en **`iniciar-f5gol.bat`**: arranca MySQL (si no está corriendo), el back y el front, y abre el navegador.

O a mano, en dos terminales:

```bash
cd backend
php artisan serve
```

```bash
cd frontend
ng serve
```

## Direcciones

- Front: http://localhost:4200
- API: http://127.0.0.1:8000/api
- Swagger: http://127.0.0.1:8000/api/documentation

Usuarios de prueba (seeders): `admin1@f5gol.com` / `password` (administrador) y `jugador1@f5gol.com` / `password` (jugador).
