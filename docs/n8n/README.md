# Automatización con n8n: mail de bienvenida

Cada vez que se crea una cuenta en F5GOL (con el formulario de registro o entrando por primera vez con Google), Laravel avisa a n8n y n8n le manda al usuario un **mail de bienvenida distinto según su rol**: al jugador le explica cómo reservar y sumarse a partidos, y al administrador cómo cargar su club, canchas y horarios.

## Cómo funciona

```
Angular ──POST /api/register──▶ Laravel ──crea el usuario y responde 201
                                   │
                                   └─(después de responder)──POST webhook──▶ n8n ──SMTP──▶ mail
```

- **Laravel** (`app/Jobs/AvisarUsuarioCreado.php`) manda un `POST` al webhook con el evento, el usuario (id, nombre, email, rol; **nunca la contraseña**), el origen (`registro` o `google`) y los links de la app. Va con el encabezado `X-F5GOL-Secreto`.
- Se ejecuta **después** de responder al usuario: el registro no espera a n8n. Si n8n está apagado o falla, Laravel deja un aviso en `storage/logs/laravel.log` y el registro funciona igual.
- Si ya tenía cuenta y entra con Google, **no** se manda otro mail.

Ejemplo de lo que recibe n8n:

```json
{
  "evento": "usuario.creado",
  "origen": "registro",
  "usuario": { "id": 42, "nombre": "Ana Gómez", "email": "ana@gmail.com", "rol": "jugador" },
  "creado_en": "2026-11-17T12:30:00-03:00",
  "links": {
    "app": "http://localhost:4200",
    "login": "http://localhost:4200/login",
    "primer_paso": "http://localhost:4200/clubes"
  }
}
```

## El flujo (12 nodos)

Archivo: [`bienvenida-usuario.json`](bienvenida-usuario.json)

| # | Nodo | Qué hace |
|---|---|---|
| 1 | **Webhook usuario creado** | Recibe el `POST` en `/webhook/f5gol-usuario-creado` |
| 2 | **Configuración** | Remitente, nombre de la app y secreto esperado: lo único que hay que editar |
| 3 | **Validar pedido** (Code) | Verifica el secreto, que el evento sea `usuario.creado` y que el email sea válido; arma el saludo con el primer nombre |
| 4 | **¿Pedido válido?** (IF) | Separa los pedidos válidos de los rechazados |
| 5 | **¿Es administrador?** (IF) | Elige el mail según el rol |
| 6 | **Mail para administrador** (Set) | Asunto y HTML: crear club → agregar canchas → cargar horarios |
| 7 | **Mail para jugador** (Set) | Asunto y HTML: buscar club → elegir horario → reservar o sumarse |
| 8 | **Enviar bienvenida** (Email/SMTP) | Manda el mail. Si falla, sigue por su salida de error |
| 9 | **Responder OK** | `200` con a quién se mandó |
| 10 | **Responder rechazo** | `401` (secreto inválido) o `422` (datos inválidos) |
| 11 | **Armar error** (Set) | Arma el mensaje cuando el servidor de correo falla |
| 12 | **Responder error** | `502` con el motivo |

## Instalación y puesta en marcha

### 1. Instalar y levantar n8n

Requiere Node.js 20 o más nuevo.

```bash
npm install -g n8n
```

```bash
n8n start
```

Abrir <http://localhost:5678> y crear la cuenta de dueño de n8n (es local, queda en tu máquina).

### 2. Importar el flujo

En n8n: **Overview > Create workflow**, menú **⋯ > Import from File** y elegir `docs/n8n/bienvenida-usuario.json`.

### 3. Crear la credencial SMTP

Con Gmail:

1. En la cuenta de Google: **Seguridad > Verificación en dos pasos** (activarla) y después **Contraseñas de aplicaciones** > crear una llamada `n8n F5GOL`. Copiar la contraseña de 16 letras.
2. En n8n, abrir el nodo **Enviar bienvenida** > **Credential to connect with** > **Create new credential** (SMTP) con estos datos:
   - User: tu Gmail completo · Password: la contraseña de aplicación
   - Host: `smtp.gmail.com` · Port: `465` · SSL/TLS: activado
3. Guardarla como **SMTP F5GOL**.

### 4. Completar la configuración

Abrir el nodo **Configuración** y completar:
- `remitente`: `F5GOL <tu-gmail@gmail.com>`, con el **mismo** mail de la credencial (Gmail no deja mandar desde otro).
- `secreto_esperado`: una clave cualquiera, por ejemplo `f5gol-2026-cambiame`. Si se deja vacío, el flujo no verifica el secreto (solo para pruebas).

### 5. Activar el flujo

Guardar y activar el flujo (interruptor **Inactive/Active**, o **Publish** en las versiones nuevas). La URL de producción queda en:

```
http://localhost:5678/webhook/f5gol-usuario-creado
```

### 6. Conectar Laravel

En `backend/.env`:

```env
N8N_WEBHOOK_USUARIO_CREADO=http://localhost:5678/webhook/f5gol-usuario-creado
N8N_WEBHOOK_SECRETO=f5gol-2026-cambiame
```

`N8N_WEBHOOK_SECRETO` tiene que ser igual al `secreto_esperado` del paso 4. Después:

```bash
php artisan config:clear
```

## Probarlo

**Desde la app:** registrar una cuenta nueva con un mail real. El mail llega en unos segundos (revisar Spam la primera vez).

**Solo el flujo**, sin la app (Git Bash):

```bash
curl -i -X POST http://localhost:5678/webhook/f5gol-usuario-creado -H "Content-Type: application/json" -H "X-F5GOL-Secreto: f5gol-2026-cambiame" -d '{"evento":"usuario.creado","origen":"registro","usuario":{"id":1,"nombre":"Ana Gómez","email":"tu-mail@gmail.com","rol":"jugador"},"links":{"app":"http://localhost:4200","primer_paso":"http://localhost:4200/clubes"}}'
```

Respuestas posibles: `200` mail enviado · `401` secreto incorrecto · `422` datos inválidos · `502` falló el SMTP (revisar la credencial).

En n8n, la pestaña **Executions** muestra cada ejecución nodo por nodo: sirve para la presentación.

## Segundo flujo: aviso de partido completo

Archivo: [`partido-completo.json`](partido-completo.json) (9 nodos). Cuando un turno de matchmaking llega al cupo, Laravel (`AvisarPartidoCompleto`) manda al webhook `/webhook/f5gol-partido-completo` los datos del turno, la cancha, el club y la lista de jugadores. n8n arma **un mail por jugador** (día, horario, cancha, club, dirección y con cuántos amigos se anotó) y responde cuántos salieron.

| # | Nodo | Qué hace |
|---|---|---|
| 1 | Webhook partido completo | Recibe el `POST` |
| 2 | Configuración | Secreto esperado y remitente |
| 3 | Validar y separar jugadores (Code) | Verifica secreto y evento, y devuelve un ítem por jugador con email válido |
| 4 | ¿Pedido válido? (IF) | Separa válidos de rechazados |
| 5 | Armar mail (Set) | Asunto y HTML personalizados |
| 6 | Enviar aviso (Email/SMTP) | Manda cada mail; si uno falla, sigue con los demás |
| 7 | Resumir envíos (Code) | Cuenta enviados y fallidos |
| 8 | Responder resultado | `200` si salieron todos, `502` si alguno falló |
| 9 | Responder rechazo | `401` o `422` |

Se importa y activa igual que el de bienvenida, con la misma credencial **SMTP F5GOL** y el mismo secreto. En `backend/.env`:

```env
N8N_WEBHOOK_PARTIDO_COMPLETO=http://localhost:5678/webhook/f5gol-partido-completo
```

Si n8n no está configurado o no responde, Laravel manda el aviso con su propio mail (`PartidoCompleto`), así nunca se pierde.

## Problemas comunes

| Síntoma | Causa |
|---|---|
| Laravel registra bien pero no llega nada y en `laravel.log` dice `Connection refused` | n8n no está levantado, o la URL del `.env` está mal |
| `404 webhook not registered` | El flujo no está activo. La URL `/webhook-test/...` solo funciona mientras se prueba desde el editor |
| `401` | `N8N_WEBHOOK_SECRETO` y `secreto_esperado` no coinciden |
| `502` con `Invalid login` | La contraseña de aplicación de Gmail está mal, o se usó la contraseña normal |
| El mail llega a Spam | Normal con cuentas nuevas; marcarlo como "No es spam" |
