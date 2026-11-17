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
- Node.js **22.22.3 o más nuevo** (lo pide Angular 22; probado con Node 24) y Angular CLI 22 (`npm install -g @angular/cli@22`). Con una versión anterior, `ng serve` falla al arrancar: revisar con `node -v`.

## Primera vez (después de clonar)

Antes, crear la base `f5gol` en phpMyAdmin con MySQL de XAMPP andando.

Doble clic en **`instalar-dependencias.bat`**: corre `composer install` en el back, crea `backend/.env` si no existe, crea el enlace `public/storage` (para las fotos de las canchas), corre `php artisan migrate --seed`, hace `npm install` en el front e instala Angular CLI 22 si falta. Si la base no existe todavía, avisa al final y se puede volver a ejecutar.

O a mano. Back:

```bash
cd backend
composer install
copy .env.example .env
php artisan key:generate
```

En `backend/.env` configurar la base de datos (`DB_DATABASE=f5gol`, usuario `root` sin contraseña en XAMPP), crear la base `f5gol` en phpMyAdmin y después:

```bash
php artisan migrate --seed
php artisan storage:link
```

Front:

```bash
cd frontend
npm install
```

### Login con Google (una vez por máquina)

1. En [Google Cloud Console](https://console.cloud.google.com/apis/credentials) crear un proyecto y una credencial **ID de cliente de OAuth** de tipo **Aplicación web** (antes hay que configurar la pantalla de consentimiento en modo *Prueba* y agregar como usuarios de prueba los mails del grupo).
2. En **URI de redireccionamiento autorizados** poner exactamente `http://localhost:8000/api/auth/google/callback`.
3. Copiar el ID y el secreto en `backend/.env`:

```env
GOOGLE_CLIENT_ID=xxxxxxxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=xxxxxxxx
GOOGLE_REDIRECT_URI="${APP_URL}/api/auth/google/callback"
FRONTEND_URL=http://localhost:4200
```

4. `php artisan config:clear` y `php artisan migrate` (agrega la columna `google_id`).

Quien entra con Google por primera vez queda registrado como **jugador**. Si ya tenía cuenta con ese mail (por ejemplo un administrador), se vincula a esa cuenta y conserva su rol.

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

## Pantallas del front

| Ruta | Quién | Qué hace |
|---|---|---|
| `/clubes`, `/clubes/:id` | Todos | Listado de clubes y sus canchas activas |
| `/canchas/:id` | Todos | Grilla de turnos: **Reservar cancha** (completa) o **Sumarme al partido** (matchmaking) |
| `/login`, `/registro` | Todos | Ingreso y registro eligiendo rol (jugador o administrador), o con Google |
| `/auth/google` | Todos | Pantalla de vuelta de Google: guarda el token y redirige según el rol |
| `/mis-reservas` | Logueado | Historial de reservas propias, con cancelación hasta 2 horas antes |
| `/panel` | Administrador | Reservas del día por cancha de sus clubes |
| `/mis-clubes` | Administrador | Sus clubes con cantidad de canchas y fotos: crear, editar, eliminar |
| `/clubes/nuevo`, `/clubes/:id/editar` | Administrador dueño | Nombre, dirección, teléfono, descripción y fotos del club |

## Endpoints principales

Todos bajo `/api`. Los de escritura piden `Authorization: Bearer <token>`; el detalle completo está en Swagger.

| Método y ruta | Notas |
|---|---|
| `POST /register`, `POST /login`, `POST /logout`, `GET /user` | Autenticación con Sanctum |
| `GET /auth/google/redirect`, `GET /auth/google/callback` | Login con Google (Socialite). Se abren desde el navegador; el callback vuelve al front con el token en `#token=` |
| `GET /clubs?buscar=&mios=1`, `GET /clubs/{id}` | `mios=1` con token devuelve los clubes del administrador. Cada club trae `canchas_count`. El detalle muestra solo canchas activas (el dueño ve todas) |
| `POST/PUT/DELETE /clubs`, `POST /clubs/{id}/fotos`, `DELETE /clubs/{id}/fotos/{indice}` | Solo el dueño (403). El dueño lo pone el servidor. 409 al borrar un club con canchas activas |
| `GET /canchas?club_id=&incluir_inactivas=1` | `incluir_inactivas` solo funciona con token de administrador |
| `PUT /canchas/{id}` | 409 si se desactiva una cancha con reservas futuras |
| `POST /canchas/{id}/fotos`, `DELETE /canchas/{id}/fotos/{indice}` | Fotos de la cancha (solo el dueño del club) |
| `GET /turnos?cancha_id=&fecha=` | Grilla de disponibilidad con `cupo_actual` |
| `GET /reservas?usuario_id=&cancha_id=&fecha=&desde=&hasta=&mis_clubes=1&por_pagina=` | `mis_clubes` filtra por los clubes del administrador logueado; `por_pagina` hasta 100 |
| `POST /reservas` | `tipo` completa o individual. 409 si el turno está ocupado, bloqueado, ya pasó, tiene jugadores anotados (para completa) o no tiene cupo. 422 si el turno no existe |
| `PUT /reservas/{id}` | Cambiar de turno libera el anterior y ocupa el nuevo. Solo el administrador cambia `estado_pago` (403) |
| `DELETE /reservas/{id}` | Cancela (queda en el historial). El jugador puede hasta 2 horas antes |

## Tests

Back (PHPUnit, usa SQLite en memoria, no toca la base de XAMPP):

```bash
cd backend
php artisan test
```

Los tests de fotos (`FotosCanchaTest`) necesitan la extensión **GD** de PHP: en XAMPP, descomentar `extension=gd` en `C:\xampp\php\php.ini`.

Front (compila todo el proyecto y detecta errores de tipos o de templates):

```bash
cd frontend
ng build
```
