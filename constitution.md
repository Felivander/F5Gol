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
| 6 | **Al menos un método externo de autenticación** (OAuth 2.0 con Laravel Socialite: Google, GitHub, Microsoft, Facebook u otro) | Google con Socialite: `GoogleAuthController`, `/login` y `/registro` (falta cargar credenciales reales y probarlo de punta a punta) |
| 7 | **Git** para control de versiones | este repo |
| 8 | **Angular consume la API** de Laravel | `frontend/src/app/services/` |
| 9 | Automatización con **n8n**: flujo completo y funcional de **al menos 10 nodos**. Lo pedido en clase (semana 13): mandar un **mail de bienvenida al crear un usuario** | pendiente |
| 10 | **Dashboard en Power BI** con indicadores (KPIs) del sistema, sacados de MySQL (extracción, modelado básico, KPIs, diseño del tablero) | pendiente |
| 11 | **Presentación técnica y funcional** del proyecto | semana 16 |

Laravel **y** Angular son obligatorios, sin excepción. No se reemplaza el frontend por Blade/Livewire ni el backend por otra cosa, aunque la propuesta original los mencione.

### 1.2 Participación pareja en el repositorio

> "Se esperan participaciones iguales de cada miembro del grupo; si esto no ocurre, el grupo completo no será aprobado, quedando regulares en la materia."

El docente analiza los repositorios. Por eso:

- **Cada integrante commitea con su propio usuario de Git y su propio email.** No se commitea con la cuenta de otro.
- **Ningún commit puede tener como autor ni como co-autor a alguien de afuera del grupo.** Los mensajes de commit y los PR no llevan firmas ni líneas `Co-authored-by` agregadas por herramientas.
- La configuración local de cada editor o herramienta no se sube al repo: queda en `.gitignore`.
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

### 1.5 Criterios de evaluación de la entrega final (`criterios de evaluacion.txt`)

- **El sistema tiene que ser 100% funcional.** No se aceptan módulos ni secciones que funcionen a medias. Si algo falla, el grupo lo arregla y se vuelve a presentar.
- Fecha límite: el **último día de clases** del cuatrimestre. Si el sistema falla ese día, la semana siguiente es recuperatorio y el grupo queda **regular** (sin promoción directa).
- **Cero excusas** ("en mi PC anda", fechas, etc.). Probar la demo en la máquina donde se va a presentar.
- Presentación de **15 a 20 minutos** por grupo. No se explica Laravel ni Angular: se explica **qué hace el sistema, cómo interactúan sus partes y en qué trabajó cada uno**. **Hablan todos los integrantes**; ensayar los tiempos.
- Se evalúa al grupo como tal: si un integrante no sabe lo que hicieron los demás, baja la nota de todos.
- Hay que **enviar los links de los repos del back y del front.** Hoy está todo en un único repo (`backend/` y `frontend/`): confirmar con el docente si alcanza con un link o si hay que separarlos.

### 1.6 Rúbrica de la API (semana 10)

| Criterio | Peso | Para el 10 |
|---|---|---|
| Diseño y arquitectura | 25% | API REST bien estructurada, rutas/controladores/modelos correctos, CRUD completo y consistente |
| Autenticación y seguridad | 20% | Autenticación correcta, middleware en rutas privadas, validaciones seguras |
| Documentación Swagger | 20% | Swagger UI accesible, **todos** los endpoints con parámetros, respuestas y ejemplos |
| Calidad del código | 15% | Código limpio, comentado, convenciones de Laravel, lógica reutilizada |
| Validaciones y errores | 10% | Validaciones consistentes y errores claros con el código HTTP correcto |
| Pruebas y despliegue | 10% | API probada, `.env` configurado, funciona en local o servidor remoto |

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
- Formularios con validaciones, mostrando los errores (incluidos los `422` de Laravel, `err.error?.errors?.campo?.[0]`) en la interfaz. El plan anual (semana 13) pide **formularios reactivos con validaciones personalizadas**; las guías de clase usan `FormsModule` + `[(ngModel)]`. Los formularios nuevos se hacen reactivos (`ReactiveFormsModule`).
- Para crear y editar desde un listado se puede reutilizar un **modal de Bootstrap** controlado con un `signal` (`modalAbierto`, `xEditando`), como en las guías "Insertar/Actualizar datos con modal".
- Borrar y restaurar piden confirmación (`confirm(...)`) y después recargan las listas de activos y de eliminados.
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

---

## 8. Estado actual y pendientes (revisado el 15/11/2026)

Revisión hecha leyendo todo el material de la cátedra (plan anual, criterios, rúbrica, propuesta, backlog, nota de n8n y las guías de las semanas 1 a 13) contra el código. Esta sección se actualiza cada vez que se cierra algo. Cuando la lista de pendientes quede vacía, el proyecto está listo para presentar.

### 8.1 Hecho y verificado

- API Laravel 13 + Sanctum: registro, login, logout, `/user`; CRUD de clubes, canchas, turnos, reservas y jugadores anotados; middleware `auth:sanctum` y `administrador`; control de dueño.
- Swagger: los 34 endpoints de `route:list` están documentados en `storage/api-docs/api-docs.json`.
- Validación con FormRequest y mensajes en español; soft delete y restauración de canchas; fotos de canchas.
- Reglas de negocio: sin doble reserva, matchmaking con cierre a 5 y mail (`PartidoCompleto`), cupo refrescado cada 15 s, cancelación hasta 2 h antes, bloqueo por mantenimiento, estado de pago solo por el administrador.
- Front Angular 22: clubes (buscador), detalle de club, grilla de turnos, reserva completa e individual, Mis reservas (cancelar y cambiar turno), alta/edición/baja/restauración de canchas, panel del administrador (día y semana, estado de pago), login, registro, interceptor con el token.
- Tests: 95 tests de Feature; pasan 87. Los 8 de `FotosCanchaTest` fallan solo porque falta la extensión **GD** en el PHP local (no es un bug del código). `ng build` compila.

### 8.2 Pendiente — requisitos obligatorios de la cátedra

1. **Login con Google (Socialite).** Código hecho en la rama `login-con-google`: migración `google_id`, `GoogleAuthController` (redirect + callback stateless, token de Sanctum en `#token=`), Swagger, 7 tests en `LoginGoogleTest`, botón en `/login` y `/registro` y pantalla `/auth/google`. **Falta:** crear las credenciales OAuth en Google Cloud (ver README), cargarlas en `.env`, probar el ingreso real con una cuenta de Google y commitear.
2. **Flujo n8n de al menos 10 nodos.** Webhook disparado por Laravel al registrar un usuario (también desde Google) → mail de bienvenida. Para llegar a 10 nodos, que sea un flujo completo (validar datos, ramificar por rol, armar mail distinto para jugador y administrador, registrar el envío, manejar errores, responder al webhook). Exportar el JSON del flujo al repo (por ejemplo `docs/n8n/`) y documentar cómo importarlo. La URL del webhook va en `.env` (`N8N_WEBHOOK_URL`), y si n8n no responde el registro no tiene que fallar.
3. **Dashboard de Power BI** conectado a MySQL con KPIs: ocupación de canchas por semana, reservas por club/cancha, turnos completados por matchmaking vs. reserva completa, ingresos por estado de pago, cancelaciones, horarios pico. Guardar el `.pbix` y capturas en `docs/powerbi/`.
4. **Guards en Angular** (semana 14): hoy ninguna ruta tiene `canActivate`. Agregar un guard de sesión para `/mis-reservas` y uno de rol administrador para `/panel`, `/canchas/nueva` y `/canchas/:id/editar`. El interceptor además tiene que limpiar la sesión y mandar a `/login` si la API responde `401`.
5. **Formularios reactivos** (semana 13 del plan anual): login, registro y alta/edición de cancha usan `FormsModule` + `ngModel`, que es lo que muestran las guías de clase, pero el plan pide formularios reactivos con validaciones personalizadas. Como mínimo, hacer reactivos los formularios nuevos (club, turnos) y el de registro, con un validador propio y los errores `422` de Laravel por campo.
5b. **Registro con rol administrador abierto.** La guía de Swagger (punto 27) recomienda que el registro público **no** deje que el cliente elija ser administrador: el rol inicial lo asigna el servidor y la creación de administradores queda restringida. Hoy `POST /register` acepta `rol: administrador` de cualquiera, lo que además contradice la regla 2.3.8. La historia del backlog pide "registro eligiendo rol", así que hay que decidir: (a) dejarlo y poder justificarlo en la presentación, o (b) registrar siempre como jugador y que un administrador existente (o un super administrador) habilite nuevos administradores.

### 8.3 Pendiente — para que ningún módulo quede "a medias"

6. **Alta y edición de clubes desde el front.** La API tiene `POST/PUT/DELETE /clubs`, pero un administrador recién registrado no tiene forma de crear su club desde Angular, así que no puede cargar canchas.
7. **Alta de turnos (horarios y tarifas) desde el front.** La API tiene `POST/PUT/DELETE /turnos`, pero hoy los turnos salen solo del seeder. El administrador tiene que poder generar los turnos de su cancha (por ejemplo, rango de fechas y horarios con tarifa) para que una cancha nueva sea reservable.
8. **Mails reales en la demo.** `.env.example` usa `MAIL_MAILER=log`: la confirmación de partido completo solo queda en el log. Configurar un SMTP de prueba (Mailtrap o Gmail con contraseña de aplicación) o mandar esos avisos también por n8n.
9. **Probar la demo de punta a punta** con la base recién creada (`migrate:fresh --seed`): registro → (Google) → crear club → crear cancha y turnos → reservar → matchmaking hasta 5 → mail → panel → cambiar pago → cancelar. Instalar GD en el PHP de la máquina de la presentación.

### 8.4 Limpieza antes de entregar

9b. Factories: solo existe `UserFactory`. La guía de Seeders muestra seeder + factory; agregar factories de `Club`, `Cancha`, `Turno` y `Reserva` simplificaría los tests y los seeders (opcional, suma en "calidad del código").
10. Sacar la página de práctica de clase (`frontend/src/app/pages/practica` y su ruta `/practica`) y los `ExampleTest` de Laravel.
11. Revisar el aviso de `ng build` por el bundle inicial (710 kB contra 500 kB de presupuesto): cargar las páginas con `loadComponent` (lazy loading) o subir el presupuesto en `angular.json` con una justificación.
12. Unificar el número de grupo (`idea_de_proyecto_F5GOL.md` dice "Grupo 4", la propuesta entregada es `Grupo5.docx`) y actualizar el README con Google, n8n y Power BI.
13. Revisar que el reparto de commits sea parejo entre los cuatro integrantes (`git shortlog -sn --all`) y que cada uno pueda explicar su parte.

### 8.5 Opcional (está en la propuesta, no lo exige la cátedra)

- Plazo de cancelación configurable por club (hoy fijo en 2 horas).
- Notificaciones dentro de la plataforma (tabla `notificaciones`).
- Rol Super Administrador para dar de alta clubes.
