# F5GOL

Plataforma web de turnos y matchmaking para canchas de fútbol. Los dueños de complejos cargan sus clubes, canchas y horarios; los jugadores reservan la cancha entera o se suman a un partido (solos, con amigos o con un equipo completo) hasta que se completen los dos equipos.

Trabajo final de **Programación IV** — UTN Facultad Regional Concordia, 2026.
**Grupo 4:** Patricio Sandri, Felipe van der Donckt, Lautaro Meza y Julián Dufey.

- Idea y requerimientos: [idea_de_proyecto_F5GOL.md](idea_de_proyecto_F5GOL.md)
- Reglas del proyecto y estado actual: [constitution.md](constitution.md)

## Estructura

| Carpeta | Qué es | Puerto |
|---|---|---|
| [`backend/`](backend) | API REST en Laravel 13 + Sanctum + Socialite (Google), documentada con Swagger | 8000 |
| [`frontend/`](frontend) | Aplicación Angular 22 que consume la API | 4200 |
| [`docs/n8n/`](docs/n8n) | Flujos de n8n para los mails (bienvenida, partido completo y partido incompleto) | 5678 |
| [`docs/powerbi/`](docs/powerbi) | Tablero de Power BI sobre la base MySQL | — |

En desarrollo, el front redirige todo lo que va a `/api` hacia el back (`frontend/proxy.conf.json`), así que no hace falta configurar CORS.

## Requisitos

| Herramienta | Versión | Para qué |
|---|---|---|
| [XAMPP](https://www.apachefriends.org/) | con MySQL/MariaDB | Base de datos (y el PHP que trae) |
| PHP | 8.3 o más nuevo, con la extensión **GD** | Laravel. GD hace falta para las fotos: en `C:\xampp\php\php.ini` descomentar `extension=gd` |
| [Composer](https://getcomposer.org/) | 2.x | Dependencias del back |
| [Node.js](https://nodejs.org/) | **22.22.3 o más nuevo** (probado con 24) | Angular 22. Con una versión anterior `ng serve` no arranca: revisar con `node -v` |
| Angular CLI | 22 | `npm install -g @angular/cli@22` (el instalador lo hace solo) |
| [n8n](https://n8n.io/) | opcional | Los mails. Sin n8n la app funciona igual |
| Power BI Desktop | opcional | Solo para abrir el tablero |

## Instalación desde cero

1. Clonar el repo dentro de `C:\xampp\htdocs\`:

   ```bash
   git clone https://github.com/Felivander/F5Gol.git
   ```

2. Abrir el panel de XAMPP, arrancar **MySQL** y crear la base **`f5gol`** en phpMyAdmin (http://localhost/phpmyadmin), con cotejamiento `utf8mb4_unicode_ci`.

3. Doble clic en **`instalar-dependencias.bat`**. Hace todo esto:
   - `composer install` en `backend/`.
   - Crea `backend/.env` a partir de `.env.example` (ya viene con la base `f5gol`, usuario `root` sin contraseña) y genera la `APP_KEY`.
   - Crea el enlace `public/storage` para que se vean las fotos.
   - `php artisan migrate --seed`: crea las tablas y carga los datos de prueba.
   - `npm install` en `frontend/` e instala Angular CLI 22 si falta.

   Si la base todavía no existía, avisa al final: creala y volvé a ejecutarlo.

<details>
<summary>O a mano, paso por paso</summary>

```bash
cd backend
composer install
copy .env.example .env
php artisan key:generate
php artisan storage:link
php artisan migrate --seed
```

```bash
cd frontend
npm install
npm install -g @angular/cli@22
```

</details>

### Login con Google (una vez por máquina)

1. En [Google Cloud Console](https://console.cloud.google.com/apis/credentials) crear un proyecto. Configurar la **pantalla de consentimiento** en modo *Prueba* y agregar como **usuarios de prueba** los mails que van a entrar (los del grupo y el del docente, si lo prueba).
2. Crear una credencial **ID de cliente de OAuth**, tipo **Aplicación web**, con este **URI de redireccionamiento autorizado**: `http://localhost:8000/api/auth/google/callback`.
3. Copiar el ID y el secreto en `backend/.env`:

   ```env
   GOOGLE_CLIENT_ID=xxxxxxxx.apps.googleusercontent.com
   GOOGLE_CLIENT_SECRET=xxxxxxxx
   ```

4. Correr `php artisan config:clear` en `backend/`.

Quien entra con Google por primera vez queda registrado como **jugador**. Si ya tenía cuenta con ese mail, se vincula a esa cuenta y conserva su rol.

> El secreto nunca se sube al repo: `backend/.env` y `client_secret_*.json` están en `.gitignore`.

### Mails con n8n (opcional)

La app avisa a n8n en tres momentos: cuando se crea una cuenta (mail de bienvenida), cuando se completa un partido y cuando un partido llega a su hora sin completarse. Para cada uno hay un flujo en `docs/n8n/` que se importa en n8n con una credencial SMTP (por ejemplo, Gmail con contraseña de aplicación). El paso a paso está en [docs/n8n/README.md](docs/n8n/README.md).

En `backend/.env` van las URLs de los webhooks (ya vienen en `.env.example`) y un secreto compartido con n8n:

```env
N8N_WEBHOOK_SECRETO=el-mismo-que-en-n8n
```

Si n8n no está levantado, el registro funciona igual (sin mail de bienvenida) y los avisos de partido salen por el mail de Laravel (`MAIL_MAILER`; con `log` quedan en `backend/storage/logs/laravel.log`).

## Levantar todo

Doble clic en **`iniciar-f5gol.bat`**. Arranca MySQL (si no está corriendo), el back, el programador de Laravel, n8n (si está instalado) y el front, y abre el navegador.

O a mano, cada uno en su terminal:

```bash
cd backend
php artisan serve
```

```bash
cd backend
php artisan schedule:work
```

```bash
cd frontend
ng serve
```

`schedule:work` es el programador de Laravel: cada 5 minutos avisa a los jugadores de los partidos que llegaron a su hora sin completarse. Si no está corriendo, ese aviso no sale (lo demás funciona).

## Direcciones

| Qué | Dirección |
|---|---|
| Front | http://localhost:4200 |
| API | http://127.0.0.1:8000/api |
| Swagger | http://127.0.0.1:8000/api/documentation |
| n8n | http://localhost:5678 |

## Usuarios de prueba

Los carga `php artisan migrate --seed`. Todos tienen la contraseña **`password`**.

| Usuario | Rol |
|---|---|
| `admin1@f5gol.com` | Administrador, dueño de Complejo Concordia FC |
| `admin2@f5gol.com` | Administrador, dueño de Arena Norte |
| `jugador1@f5gol.com` … `jugador8@f5gol.com` | Jugadores |
| `demo1@f5gol.com` … | Jugadores extra para armar partidos |

## Preparar la demo

El mismo día de la presentación, a la mañana, dejar la base limpia y con turnos para hoy y mañana:

```bash
cd backend
php artisan migrate:fresh --seed
```

`migrate:fresh` **borra todos los datos** de la base `f5gol` y la vuelve a crear. `HistorialDemoSeeder` carga 8 semanas de reservas para que el tablero de Power BI tenga datos (tarda unos 40 segundos).

El seed deja **un partido en cada estado** (`PartidosDemoSeeder`), en canchas y horarios distintos que empiezan dentro de al menos una hora:

| Partido | Cómo queda | Para mostrar |
|---|---|---|
| Le falta uno | 9/10 | Al sumarse alguien se completa, pasa a ocupado y se dispara el aviso de partido completo de n8n |
| Mitad lleno | 5/10: el Equipo A completo y el B vacío | Que se puede reservar el otro equipo entero |
| Completo | 10/10, ocupado | Que no aparece en el lobby y en la grilla figura ocupado |
| Vacío | 0/10 | Arrancar un partido desde cero |

Al terminar, la consola dice el club, la cancha, la hora y el número de turno de cada uno. Los arma con los jugadores `demoN@f5gol.com`, así `jugador1@f5gol.com` … `jugador8@f5gol.com` (o una cuenta de Google) quedan libres para sumarse en vivo.

Si la base se recreó otro día, o si en el ensayo se completó el que faltaba uno, se pueden volver a armar sin borrar nada (solo arma los que no estén):

```bash
php artisan db:seed --class=TurnoSeeder
php artisan db:seed --class=PartidosDemoSeeder
```

Para volver a registrarse con el mismo mail (y ver de nuevo el mail de bienvenida) sin recrear la base, se borran las cuentas creadas en el ensayo. El comando muestra qué va a borrar y pide confirmación; con cada cuenta se van sus clubes, reservas, inscripciones y tokens, y los partidos que quedan incompletos vuelven a estar disponibles:

```bash
php artisan usuarios:borrar --registrados
php artisan usuarios:borrar tu@mail.com
```

`--registrados` borra todas las cuentas que no son del seeder (las que no terminan en `@f5gol.com`); con mails, solo esas.

La lista completa para tildar en la máquina de la presentación está en [docs/pruebas/F5GOL-lista-de-pruebas.docx](docs/pruebas/F5GOL-lista-de-pruebas.docx).

Recorrido sugerido: registrarse (o entrar con Google) → crear un club en **Mis clubes** → agregar una cancha → cargar sus horarios → reservar la cancha entera con un jugador → en **Partidos abiertos**, sumarse al partido 9/10 (se completa y llega el mail) → ver las reservas en el **Panel** y marcar la seña → cancelar una reserva desde **Mis reservas**.

## Cómo funciona el matchmaking

- Cada turno tiene un cupo igual a la capacidad de la cancha, repartido en dos equipos: **Equipo A** y **Equipo B** (en F5, 5 y 5; si el cupo es impar, el lugar extra es del A).
- Se entra de tres formas: **un lugar** (solo o con amigos, en el equipo que elijas), **un equipo completo** (solo si ese equipo está vacío) o **la cancha entera** (solo si no hay nadie anotado).
- Mientras falten jugadores, el turno se sigue mostrando **disponible**. Cuando se llenan los dos equipos pasa a **ocupado** y les llega un mail a todos.
- El cupo se actualiza solo cada 15 segundos. No hay doble reserva: se controla en la base con una transacción y bloqueo del turno.
- Cada jugador puede cancelar o cambiar de turno **hasta que empieza el partido**. El administrador del club puede hacerlo siempre.

## Pantallas del front

| Ruta | Quién | Qué hace |
|---|---|---|
| `/clubes`, `/clubes/:id` | Todos | Buscador de clubes y detalle del club con sus fotos y canchas |
| `/canchas/:id` | Todos | Grilla de turnos por día: reservar la cancha entera, un equipo o sumarse a un partido |
| `/partidos` | Todos | Partidos que se están armando, con los lugares libres de cada equipo. Tocando un lugar libre te sumás |
| `/partidos/:id` | Anotados y dueño del club | Sala del partido: los dos equipos en la cancha, tu lugar, hasta cuándo te podés bajar y link para invitar |
| `/login`, `/registro` | Todos | Ingreso y registro (jugador o administrador), o con Google |
| `/auth/google` | Todos | Vuelta de Google: guarda el token y redirige según el rol |
| `/mis-reservas` | Logueado | Historial de reservas, cambiar de turno y cancelar |
| `/panel` | Administrador | Reservas del día y de la semana de sus canchas, con el estado de pago |
| `/mis-clubes` | Administrador | Sus clubes: crear, editar y eliminar |
| `/clubes/nuevo`, `/clubes/:id/editar` | Administrador dueño | Datos y fotos del club |
| `/canchas/nueva`, `/canchas/:id/editar` | Administrador dueño | Datos, comodidades y fotos de la cancha |
| `/canchas/:id/horarios` | Administrador dueño | Carga en lote de turnos: período, días, franja horaria y duración |

Las pantallas privadas están protegidas con guards. Si el token vence, el front cierra la sesión y manda al login.

## Endpoints principales

Todos bajo `/api`. Los privados piden `Authorization: Bearer <token>`. El detalle completo, con parámetros, respuestas y ejemplos, está en Swagger.

| Método y ruta | Notas |
|---|---|
| `POST /register`, `POST /login`, `POST /logout`, `GET /user` | Autenticación con Sanctum. Login y registro aceptan 10 intentos por minuto |
| `GET /auth/google/redirect`, `GET /auth/google/callback` | Login con Google. Se abren desde el navegador; el callback vuelve al front con el token |
| `GET /clubs?buscar=&mios=1`, `GET /clubs/{id}` | Búsqueda por nombre o dirección; `mios=1` devuelve los clubes del administrador |
| `POST/PUT/DELETE /clubs`, `POST/DELETE /clubs/{id}/fotos` | Solo el dueño (403). El dueño lo asigna el servidor |
| `GET /canchas`, `POST/PUT/DELETE /canchas`, `PUT /canchas/{id}/restore`, `GET /canchas/eliminadas` | Borrado con soft delete y restauración. Solo el dueño |
| `GET /turnos?cancha_id=&fecha=` | Grilla con el cupo de cada equipo |
| `POST /turnos/generar`, `PATCH /turnos/{id}/bloqueo` | Carga en lote y bloqueo por mantenimiento. Solo el dueño |
| `GET /partidos-abiertos` | Partidos que se están armando. Con token dice si estás anotado y en qué equipo |
| `GET /partidos/{id}` | Sala del partido (solo anotados y dueño del club) |
| `GET /reservas`, `POST /reservas`, `PUT /reservas/{id}`, `DELETE /reservas/{id}` | `tipo`: `completa`, `equipo` o `individual`, con `equipo` A o B. 409 si el turno no está disponible. Solo el administrador cambia el estado de pago |

## Tests

Back (PHPUnit, usa SQLite en memoria: no toca la base de XAMPP). Los de fotos necesitan **GD**:

```bash
cd backend
php artisan test
```

Front:

```bash
cd frontend
ng build
ng test --watch=false
```

Si se cambia la API, regenerar la documentación y commitear `backend/storage/api-docs/api-docs.json`:

```bash
cd backend
php artisan l5-swagger:generate
```

## Problemas frecuentes

| Qué pasa | Qué hacer |
|---|---|
| `SQLSTATE[HY000] [1049] Unknown database 'f5gol'` | Crear la base `f5gol` en phpMyAdmin y correr `php artisan migrate --seed` |
| `ng serve` no arranca o se queja de la versión de Node | Instalar Node 22.22.3 o más nuevo (o 24) y volver a abrir la terminal |
| No se ven las fotos | Falta el enlace: `php artisan storage:link` en `backend/` |
| Fallan los tests de fotos con "GD extension is not installed" | Descomentar `extension=gd` en `C:\xampp\php\php.ini` |
| Google dice "Error 400: redirect_uri_mismatch" | El URI de redireccionamiento en Google Cloud tiene que ser exactamente `http://localhost:8000/api/auth/google/callback` |
| Google dice "Acceso bloqueado" | El mail no está como usuario de prueba en la pantalla de consentimiento |
| `ng serve` muestra una versión vieja | Cortarlo (Ctrl+C) y volver a levantarlo |
| No llegan los mails | Ver la tabla de problemas de [docs/n8n/README.md](docs/n8n/README.md) |

## Automatización y reportes

- **n8n**: [docs/n8n/README.md](docs/n8n/README.md).
- **Power BI** (ocupación, reservas, ingresos y jugadores): [docs/powerbi/README.md](docs/powerbi/README.md).
