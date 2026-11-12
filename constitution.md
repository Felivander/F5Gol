# Constitución del proyecto F5GOL

Reglas que todo el código y todo el trabajo del repo tienen que cumplir. Salen del **Plan Anual de Actividades Académicas de Programación IV 2026** (UTN FRC, Lic. Pablo Pérez), de la propuesta del grupo (`Grupo5.docx`, `idea_de_proyecto_F5GOL.md`), del backlog (`backlog_F5GOL.csv`) y de los apuntes de clase. Si algo de este documento choca con lo que pida el docente, manda lo que pida el docente: actualizá este archivo.

**Grupo:** Patricio Sandri, Felipe van der Donckt, Lautaro Meza, Julián Dufey.

---

## 1. Reglas de la cátedra (no negociables)

### 1.1 Requisitos mínimos del proyecto

La app tiene que tener, como mínimo:

| # | Requisito | Dónde se cumple |
|---|---|---|
| 1 | Backend en **Laravel** (última versión) | `backend/` (Laravel 13) |
| 2 | API documentada con **Swagger** (L5-Swagger) | `/api/documentation` |
| 3 | Frontend en **Angular** (última versión) | `frontend/` (Angular 22) |
| 4 | Base de datos **relacional** | MySQL / MariaDB (XAMPP) |
| 5 | Protección con **Laravel Sanctum** | middleware `auth:sanctum` |
| 6 | **Al menos un método externo de autenticación** (OAuth 2.0 con Laravel Socialite: Google, GitHub, Microsoft, Facebook u otro) | pendiente |
| 7 | **Git** para control de versiones | este repo |
| 8 | **Angular consume la API** de Laravel | `frontend/src/app/services/` |
| 9 | Automatización con **n8n**: flujo completo y funcional de **al menos 10 nodos** | pendiente |
| 10 | **Dashboard en Power BI** con indicadores (KPIs) del sistema, sacados de MySQL | pendiente |
| 11 | **Presentación técnica y funcional** del proyecto | semana 16 |

Laravel **y** Angular son obligatorios, sin excepción. No se reemplaza el frontend por Blade/Livewire ni el backend por otra cosa, aunque la propuesta original los mencione.

### 1.2 Participación pareja en el repositorio

> "Se esperan participaciones iguales de cada miembro del grupo; si esto no ocurre, el grupo completo no será aprobado, quedando regulares en la materia."

El docente analiza los repositorios. Por eso:

- **Cada integrante commitea con su propio usuario de Git y su propio email.** No se commitea con la cuenta de otro.
- **Ningún commit puede tener como autor ni como co-autor a una herramienta o a alguien de afuera del grupo** (por ejemplo, asistentes de IA). `.claude/settings.json` desactiva la firma automática; no lo borres.
- El trabajo se reparte de forma que todos tengan commits de backend, de frontend y de documentación a lo largo del cuatrimestre.
- Commits chicos y frecuentes, no un commit gigante al final.

### 1.3 Rotación de roles

En cada entrega, cada integrante toma un rol **distinto**, y todos pasan por todos los roles al menos una vez:

1. Backend (Laravel)
2. Frontend (Angular)
3. Automatización con n8n y tablero de Power BI
4. Documentación y gestión del repositorio
5. Coordinación general y presentación

### 1.4 Evaluación

La nota final depende de: trabajo final grupal e individual, análisis de los repositorios, presentaciones en clase, participación activa y entregas en tiempo y forma. Cada integrante tiene que poder **explicar el funcionamiento técnico** de lo que hizo y **justificar las decisiones técnicas**.

---

## 2. Alcance del producto

F5GOL es una app web de gestión de turnos y matchmaking para canchas de fútbol 5. Tiene dos roles: **Administrador** (dueño del club) y **Jugador**.

### 2.1 Dentro del alcance (MVP)

- Gestión de clubes, canchas (fotos, descripción, capacidad, tarifa), horarios y tarifas.
- Registro y login con rol (jugador / administrador).
- Grilla de disponibilidad por cancha, fecha y horario.
- Reserva de cancha completa.
- Matchmaking: inscripción individual hasta completar el cupo de 5, con confirmación y aviso automático al completarse.
- "Mis reservas": historial, edición y cancelación dentro del plazo permitido.
- Estado de pago manual: pendiente / seña / pagado.
- Panel del administrador: reservas del día y de la semana por cancha.
- Bloqueo manual de turnos por mantenimiento.
- Búsqueda de canchas y horarios disponibles por parte del jugador.

### 2.2 Fuera del alcance

- Pasarela de pago en línea real.
- App móvil nativa (se hace web responsive).
- Chat en tiempo real entre jugadores.
- Rankings o estadísticas avanzadas de jugadores.

### 2.3 Reglas de negocio que nunca se rompen

1. **No hay doble reserva:** un turno reservado no puede tomarlo otro usuario. Se controla en la base de datos (transacción / bloqueo), no solo en el front.
2. Un turno con matchmaking **incompleto nunca se muestra como "Ocupado"**.
3. Al llegar a 5 jugadores, el turno pasa a "Ocupado" y se notifica a los 5.
4. El cupo de anotados **se actualiza sin recargar la página**.
5. Una cancelación dentro de la ventana permitida **libera el turno automáticamente**.
6. **Cada administrador solo gestiona sus propios clubes y canchas.**
7. Un turno bloqueado por mantenimiento no se puede reservar ni tomar por matchmaking.
8. El rol de un usuario lo decide el servidor: no se confía en lo que mande el cliente para darle permisos.

### 2.4 Requisitos no funcionales

- **Mobile-first:** todo tiene que usarse bien desde el celular.
- Consulta de disponibilidad en **menos de 2 segundos** con uso normal.
- Interfaz simple y autoexplicativa: pocos pasos para reservar, el cliente no tiene personal técnico.
- Seguridad: contraseñas hasheadas, validación de toda entrada y autorización por rol en cada ruta.

---

## 3. Stack y entorno

| Capa | Tecnología |
|---|---|
| Backend | PHP 8.3+ y Laravel 13 |
| Base de datos | MySQL / MariaDB (XAMPP) |
| Autenticación | Laravel Sanctum (tokens) + Socialite (proveedor externo) |
| Documentación de la API | L5-Swagger con atributos de PHP (`OpenApi\Attributes as OA`) |
| Frontend | Angular 22 (componentes standalone), Bootstrap |
| Node | Node.js 24 (manejado con NVM si hace falta) |
| Notificaciones | Laravel Notifications (mail) |
| Imágenes | Laravel Storage (disco `public`) |
| Automatización | n8n (webhooks + API REST) |
| Reportes | Power BI conectado a MySQL |

Extensiones de PHP necesarias: BCMath, Ctype, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML, cURL, Fileinfo, **GD** (imágenes) e Intl.

---

## 4. Convenciones del backend (Laravel)

### 4.1 Estructura

- Recursos creados con `php artisan make:model Nombre -mcr --api`. Los controladores de API no tienen `create()` ni `edit()`.
- Rutas en `routes/api.php`, con `Route::apiResource(...)` y `->only([...])` / `->except([...])`.
- Lectura pública (`index`, `show`); escritura dentro de `Route::middleware('auth:sanctum')`. Lo que es solo del administrador, además dentro del middleware `administrador`.
- Las rutas fijas (por ejemplo `canchas/eliminadas`) se declaran **antes** de las rutas con `{id}`.

### 4.2 Respuestas

- Siempre `response()->json(...)` con el código HTTP correcto: `200` OK, `201` creado, `401` no autenticado, `403` sin permiso, `404` no encontrado, `409` conflicto, `422` datos inválidos.
- Al crear, editar o borrar se devuelve `message` y, si corresponde, el recurso: `['message' => 'Cancha creada correctamente', 'cancha' => $cancha]`. Al editar, el recurso con `->fresh()`.
- Si un registro no existe: `find()` + respuesta `404` con `message`, o `findOrFail()`.
- Los listados se **paginan desde el backend** (`->paginate(10)`), y solo se devuelven los campos necesarios (`select()`, `with('relacion:id,nombre')`).
- Los mensajes para el usuario van en español.

### 4.3 Validación

- La validación va en clases **FormRequest** (`app/Http/Requests`), con mensajes personalizados en español.
- Nunca `Model::create($request->all())` sin validar: se usa `$request->validated()` y `$fillable` en el modelo.

### 4.4 Base de datos

- Toda modificación del esquema es una **migración nueva** (`add_x_to_y_table`); nunca se edita una migración que ya está en `main`.
- Toda migración tiene un `down()` que deshace su `up()`.
- Claves foráneas con `foreignId()->constrained()`; el orden de las migraciones respeta las dependencias.
- Dinero con `decimal`, no `float`.
- Relaciones definidas en los modelos Eloquent (`hasMany`, `belongsTo`) y cargadas con `with()`.
- Borrado de canchas con **soft delete** (`deleted_at`) y endpoint para restaurar.
- Los datos de prueba se cargan con **seeders** y **factories**; `php artisan migrate:fresh --seed` tiene que dejar la base lista para probar.

### 4.5 Seguridad

- El modelo `User` usa `HasApiTokens`. Login devuelve el token de Sanctum; logout lo revoca.
- Autorización por rol y por dueño en cada acción de escritura (políticas o chequeo en el controlador).
- El archivo `.env` **nunca** se sube al repo. Se mantiene `.env.example` actualizado.

### 4.6 Swagger

- **Todo endpoint** se documenta con atributos `#[OA\Get]`, `#[OA\Post]`, `#[OA\Put]`, `#[OA\Patch]` o `#[OA\Delete]`, con `summary`, `tags`, parámetros y respuestas posibles.
- Los endpoints protegidos llevan `security: [['sanctum' => []]]`.
- Después de cambiar la API: `php artisan l5-swagger:generate` y se commitea `storage/api-docs/api-docs.json`.

### 4.7 Tests

- Cada funcionalidad del backend tiene tests de Feature en `backend/tests/Feature`.
- `php artisan test` tiene que pasar antes de mergear a `main`.

---

## 5. Convenciones del frontend (Angular)

- Componentes **standalone** (no hay `app.module.ts`). Cada pantalla en `src/app/pages/`, las piezas reutilizables en `src/app/components/`.
- Sintaxis de control nueva: `@if`, `@for (... ; track ...)`, `@else`, `@empty`.
- Estado con `signal()` / `computed()`; parámetros de ruta con `input()`.
- **Toda llamada HTTP pasa por un servicio** (`src/app/services/`) inyectado con `inject()`; los componentes no usan `HttpClient` directamente.
- Los tipos de los datos de la API están en `src/app/models.ts`.
- Formularios **reactivos** con validaciones, mostrando los errores (incluidos los `422` de Laravel) en la interfaz.
- El token de Sanctum se guarda en `localStorage`/`sessionStorage`, se agrega con un **HTTP Interceptor** (`Authorization: Bearer ...`) y las rutas privadas se protegen con **guards**.
- En desarrollo, `/api` se redirige a Laravel con `proxy.conf.json`.
- Diseño mobile-first con Bootstrap; textos en español.
- `ng build` y `ng test` tienen que pasar antes de mergear a `main`.

---

## 6. Trabajo con Git

- `main` siempre funciona. Nadie pushea directo a `main`: se trabaja en una rama y se mergea con un **Pull Request** revisado por otro integrante.
- Ramas con nombre descriptivo (por ejemplo `buscador-de-clubes`).
- Mensajes de commit en español, en infinitivo y con la clave de Jira cuando hay una historia: `F5GOL-36 Agregar pantalla de alta y edicion de cancha con fotos`.
- Antes de abrir el PR: actualizar la rama con `main`, resolver conflictos y correr los tests del back y el build del front.
- Las tareas se siguen en el tablero de Jira del grupo (historias de usuario, prioridad MoSCoW y story points del backlog).

---

## 7. Documentación

- `README.md` explica cómo instalar y levantar todo (MySQL, back y front) desde cero.
- El código es claro, reutilizable y con comentarios donde la decisión no es obvia.
- Se consulta la documentación oficial de Laravel, Angular, Sanctum, Swagger, n8n y Power BI, y se investiga por cuenta propia lo que no se vio en clase.
