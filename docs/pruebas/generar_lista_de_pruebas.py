# Genera docs/pruebas/F5GOL-lista-de-pruebas.docx con python-docx: python -I docs/pruebas/generar_lista_de_pruebas.py (con SALIDA_DOCX se guarda en otra ruta)
import os
from docx import Document
from docx.enum.section import WD_ORIENT
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Cm, Pt, RGBColor

SALIDA = os.environ.get('SALIDA_DOCX') or r'C:\xampp\htdocs\F5Gol\docs\pruebas\F5GOL-lista-de-pruebas.docx'
TINTA = RGBColor(0x15, 0x17, 0x1A)
CANCHA = RGBColor(0x0E, 0x5E, 0x3A)
GRIS = RGBColor(0x5B, 0x5F, 0x66)
FUENTE = 'Calibri'

doc = Document()
sec = doc.sections[0]
sec.orientation = WD_ORIENT.LANDSCAPE
sec.page_width, sec.page_height = Cm(29.7), Cm(21.0)
for lado in ('left_margin', 'right_margin'):
    setattr(sec, lado, Cm(1.8))
sec.top_margin = sec.bottom_margin = Cm(1.6)

# Estilos: todo en Calibri, títulos en verde cancha
normal = doc.styles['Normal']
normal.font.name = FUENTE
normal.font.size = Pt(10.5)
normal.font.color.rgb = TINTA
normal.element.rPr.rFonts.set(qn('w:eastAsia'), FUENTE)
normal.paragraph_format.space_after = Pt(4)
for nombre, tam, color, antes, despues in [('Title', 26, TINTA, 0, 2), ('Heading 1', 15, CANCHA, 14, 6)]:
    st = doc.styles[nombre]
    st.font.name = FUENTE
    st.font.size = Pt(tam)
    st.font.bold = True
    st.font.color.rgb = color
    rpr = st.element.get_or_add_rPr()
    rf = rpr.find(qn('w:rFonts'))
    if rf is None:
        rf = OxmlElement('w:rFonts'); rpr.append(rf)
    for k in ('w:ascii', 'w:hAnsi', 'w:eastAsia', 'w:cs'):
        rf.set(qn(k), FUENTE)
    for k in ('w:asciiTheme', 'w:hAnsiTheme', 'w:eastAsiaTheme', 'w:cstheme'):
        if rf.get(qn(k)) is not None:
            del rf.attrib[qn(k)]
    st.paragraph_format.space_before = Pt(antes)
    st.paragraph_format.space_after = Pt(despues)
    st.paragraph_format.keep_with_next = True
# Sin la línea azul que Word pone bajo el título
titulo_pPr = doc.styles['Title'].element.get_or_add_pPr()
for b in titulo_pPr.findall(qn('w:pBdr')):
    titulo_pPr.remove(b)


def sombrear(celda, color):
    tcPr = celda._tc.get_or_add_tcPr()
    shd = OxmlElement('w:shd')
    shd.set(qn('w:val'), 'clear'); shd.set(qn('w:color'), 'auto'); shd.set(qn('w:fill'), color)
    tcPr.append(shd)


def margen_celdas(tabla, cm=0.15):
    tblPr = tabla._tbl.tblPr
    mar = OxmlElement('w:tblCellMar')
    for lado in ('top', 'bottom', 'left', 'right'):
        e = OxmlElement(f'w:{lado}')
        e.set(qn('w:w'), str(int(cm * 567 if lado in ('left', 'right') else 50)))
        e.set(qn('w:type'), 'dxa')
        mar.append(e)
    tblPr.append(mar)


def repetir_encabezado(fila):
    trPr = fila._tr.get_or_add_trPr()
    e = OxmlElement('w:tblHeader'); e.set(qn('w:val'), 'true'); trPr.append(e)


def no_partir(fila):
    trPr = fila._tr.get_or_add_trPr()
    e = OxmlElement('w:cantSplit'); e.set(qn('w:val'), 'true'); trPr.append(e)


def texto_celda(celda, partes, negrita=False, color=TINTA, tam=10, centro=False):
    p = celda.paragraphs[0]
    p.paragraph_format.space_after = Pt(0)
    if centro:
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    for t, op in (partes if isinstance(partes, list) else [(partes, {})]):
        r = p.add_run(t)
        r.bold = op.get('negrita', negrita)
        r.font.size = Pt(op.get('tam', tam))
        r.font.color.rgb = op.get('color', color)


ANCHOS = [Cm(1.0), Cm(4.4), Cm(6.8), Cm(6.4), Cm(7.5)]
CODIGO = 'Consolas'


def lineas_de_codigo(celda, lineas):
    # Un renglón por línea, en Consolas chico: endpoint, archivo y función del back y del front
    p = celda.paragraphs[0]
    p.paragraph_format.space_after = Pt(0)
    for i, linea in enumerate(lineas):
        r = p.add_run(linea)
        r.font.name = CODIGO
        r._element.get_or_add_rPr().get_or_add_rFonts().set(qn('w:eastAsia'), CODIGO)
        r.font.size = Pt(8.5)
        r.font.color.rgb = TINTA
        if i < len(lineas) - 1:
            r.add_break()


def tabla(filas):
    t = doc.add_table(rows=1, cols=5)
    t.alignment = WD_TABLE_ALIGNMENT.CENTER
    t.style = 'Table Grid'
    t.autofit = False
    margen_celdas(t)
    for i, cab in enumerate(['Nº', 'Qué probar', 'Cómo', 'Qué tiene que pasar', 'Dónde está en el código']):
        c = t.rows[0].cells[i]
        c.width = ANCHOS[i]
        sombrear(c, '15171A')
        texto_celda(c, cab, negrita=True, color=RGBColor(0xFF, 0xFF, 0xFF), tam=10, centro=i == 0)
        # El encabezado nunca queda solo al pie de una página: va con la primera fila
        c.paragraphs[0].paragraph_format.keep_with_next = True
    repetir_encabezado(t.rows[0])
    for num, que, como, resultado, nuevo, codigo in filas:
        fila = t.add_row()
        no_partir(fila)
        celdas = fila.cells
        for i, c in enumerate(celdas):
            c.width = ANCHOS[i]
        texto_celda(celdas[0], num, negrita=True, centro=True)
        partes = [(que, {'negrita': True})]
        if nuevo:
            partes += [('  NUEVO', {'negrita': True, 'color': CANCHA, 'tam': 8.5})]
        texto_celda(celdas[1], partes)
        texto_celda(celdas[2], como)
        texto_celda(celdas[3], resultado)
        lineas_de_codigo(celdas[4], codigo)
        if nuevo:
            sombrear(celdas[1], 'F2FBCF')
    doc.add_paragraph()


def nota(t):
    p = doc.add_paragraph()
    r = p.add_run(t)
    r.font.size = Pt(10)
    r.font.color.rgb = GRIS


# ------------------------------------------------------------------ portada
doc.add_paragraph('F5GOL · Lista de pruebas', style='Title')
p = doc.add_paragraph()
r = p.add_run('Grupo 4 · Programación IV · UTN FRC · actualizada el 18/11/2026')
r.font.color.rgb = GRIS
doc.add_paragraph(
    'Recorrido para probar el sistema completo en la máquina donde se va a presentar. Tildá cada prueba al hacerla; '
    'si una falla, anotá qué pasó y avisá al grupo. Las marcadas como NUEVO son de los últimos cambios: '
    'los partidos de la demo en cada estado, los mails con el diseño nuevo, cómo borrar las cuentas del ensayo y el nuevo plazo para cancelar.')
nota('Usuarios del seeder (contraseña "password"): admin1@f5gol.com (dueño de Complejo Concordia FC), '
     'admin2@f5gol.com (dueño de Arena Norte), jugador1@f5gol.com … jugador8@f5gol.com. '
     'Para ver un mail de verdad, usá una cuenta propia (registro o Google).')
nota('La última columna dice dónde está cada prueba en el código: el endpoint de la API y el archivo y la función del back '
     'y del front. Para que entre, las carpetas van abreviadas: Controllers/ = backend/app/Http/Controllers/, '
     'pages/ = frontend/src/app/pages/, components/ = frontend/src/app/components/, services/ = frontend/src/app/services/.')

doc.add_paragraph('0. Preparación', style='Heading 1')
tabla([
    ('0.1', 'Base recreada ese mismo día', 'A la mañana, en backend: php artisan migrate:fresh --seed (tarda unos 40 s).',
     'Termina sin errores y la consola dice dónde quedó cada partido de la demo (le falta uno, mitad lleno, completo y vacío).', True,
     ['backend/database/seeders/DatabaseSeeder.php', 'backend/database/seeders/', '  PartidosDemoSeeder.php run()']),
    ('0.2', 'Borrar las cuentas del ensayo', 'En backend: php artisan usuarios:borrar --registrados (todas las que no son del seeder) o php artisan usuarios:borrar tu@mail.com. Para empezar de cero: php artisan migrate:fresh --seed (borra todo).',
     'Muestra las cuentas, pide confirmación y las borra con sus clubes, reservas y tokens. Esos mails se pueden volver a registrar y llega otra vez la bienvenida.', True,
     ['backend/app/Console/Commands/', '  BorrarUsuarios.php handle()']),
    ('0.3', 'Todo levantado', 'Doble clic en iniciar-f5gol.bat.',
     'Se abren el back (8000), el front (4200), el programador (schedule:work) y n8n (5678).', False,
     ['iniciar-f5gol.bat', 'backend/routes/console.php (schedule)']),
    ('0.4', 'Flujos de n8n al día', 'En n8n, los 3 flujos (bienvenida, partido completo, partido incompleto) activos, con el HTML nuevo del repo, el secreto y la credencial SMTP.',
     'Los 3 figuran activos. Si se importaron de nuevo, el secreto coincide con N8N_WEBHOOK_SECRETO del .env.', True,
     ['docs/n8n/bienvenida-usuario.json', 'docs/n8n/partido-completo.json', 'docs/n8n/partido-incompleto.json', 'backend/config/services.php (n8n)']),
    ('0.5', 'PHP con GD', 'En backend: php artisan test.', 'Pasan todos los tests, incluidos los de fotos.', False,
     ['backend/tests/Feature/', 'C:\\xampp\\php\\php.ini (extension=gd)']),
    ('0.6', 'Google Cloud', 'En la pantalla de consentimiento, agregar como usuarios de prueba los mails que van a entrar (el del docente también).',
     'Esos mails pueden entrar con Google sin "Acceso bloqueado".', False,
     ['backend/config/services.php (google)', 'backend/.env GOOGLE_CLIENT_ID']),
])

doc.add_paragraph('1. Cuentas y acceso', style='Heading 1')
tabla([
    ('1.1', 'Registro de jugador', 'Registro con una cuenta propia, eligiendo jugador.',
     'Entra a la app. Llega el mail de bienvenida de jugador con el diseño nuevo: barra negra con el logo F5 en lima, encabezado verde y botón lima, sin emojis.', True,
     ['POST /api/register', 'Controllers/AuthController.php register()', 'backend/app/Jobs/', '  AvisarUsuarioCreado.php handle()', 'pages/registro registrarme()', 'docs/n8n/bienvenida-usuario.json']),
    ('1.2', 'Registro de administrador', 'Registro con otra cuenta propia, eligiendo administrador.',
     'Entra como administrador. Llega el mail de bienvenida de administrador ("Cargar mi club") con el diseño nuevo.', True,
     ['POST /api/register (rol)', 'Controllers/AuthController.php register()', 'docs/n8n/bienvenida-usuario.json', '  (mail según el rol)']),
    ('1.3', 'Login con Google', 'En /login, "Entrar con Google" con un mail de prueba.',
     'Vuelve logueado. Si el mail no tenía cuenta, queda como jugador; si la tenía, conserva su rol.', False,
     ['GET /api/auth/google/redirect', 'GET /api/auth/google/callback', 'Controllers/GoogleAuthController.php', '  redirect() / callback()', 'pages/auth-google ngOnInit()', 'components/boton-google']),
    ('1.4', 'Credenciales incorrectas', 'Login con una contraseña equivocada.', 'Mensaje claro de credenciales incorrectas; no entra.', False,
     ['POST /api/login', 'Controllers/AuthController.php login()', 'pages/login ingresar()', 'services/auth.ts login()']),
    ('1.5', 'Límite de intentos', 'Más de 10 intentos de login en un minuto.', 'Responde 429 con un mensaje de esperar.', False,
     ['backend/routes/api.php throttle:10,1', 'backend/bootstrap/app.php', '  ThrottleRequestsException (429)']),
    ('1.6', 'Rutas privadas', 'Sin sesión, entrar a /mis-reservas. Logueado como jugador, entrar a /panel.',
     'Sin sesión manda a /login; el jugador vuelve a /clubes.', False,
     ['frontend/src/app/guards/auth.guard.ts', '  sesionGuard / administradorGuard', 'frontend/src/app/app.routes.ts']),
    ('1.7', 'Sesión vencida', 'Borrar el token en la base (o cerrar sesión en otra pestaña) y hacer algo que pida la API.',
     'Cierra la sesión y manda a /login avisando que la sesión venció.', False,
     ['services/auth.interceptor.ts', '  authInterceptor', 'pages/login (aviso sesion=vencida)']),
])

doc.add_paragraph('2. Dueño del club', style='Heading 1')
tabla([
    ('2.1', 'Crear club', 'Como administrador, Mis clubes → Nuevo club, con fotos y descripción.', 'El club aparece en Mis clubes y en el buscador.', False,
     ['POST /api/clubs', 'POST /api/clubs/{id}/fotos', 'Controllers/ClubController.php', '  store() / subirFoto()', 'pages/club-form guardar() / subirFoto()']),
    ('2.2', 'Crear cancha', 'Agregar una cancha con superficie, techada, luz, vestuarios, tarifa y fotos.', 'Se ve en el detalle del club con sus comodidades y fotos.', False,
     ['POST /api/canchas', 'POST /api/canchas/{id}/fotos', 'Controllers/CanchaController.php', '  store() / subirFoto()', 'pages/cancha-form guardar() / subirFoto()']),
    ('2.3', 'Cargar horarios en lote', 'En la cancha, Cargar horarios: período, días y franja horaria.',
     'Muestra la vista previa y crea los turnos. Repetirlo no duplica ni superpone.', False,
     ['POST /api/turnos/generar', 'Controllers/TurnoController.php generar()', 'pages/cancha-horarios generar()']),
    ('2.4', 'Bloquear un turno', 'Desde la grilla, bloquear un turno libre por mantenimiento.', 'Los jugadores no pueden reservarlo ni sumarse.', False,
     ['PATCH /api/turnos/{id}/bloqueo', 'Controllers/TurnoController.php bloqueo()', 'pages/cancha-turnos cambiarBloqueo()']),
    ('2.5', 'Borrar y restaurar cancha', 'Eliminar una cancha sin reservas futuras y después restaurarla.',
     'Pide confirmación, pasa a "Canchas eliminadas" y vuelve al restaurarla. Con reservas futuras no deja borrarla (409).', False,
     ['DELETE /api/canchas/{id}', 'PUT /api/canchas/{id}/restore', 'Controllers/CanchaController.php', '  destroy() / restore()', 'pages/club-detalle', '  eliminarCancha() / restaurarCancha()']),
    ('2.6', 'Solo lo propio', 'Logueado como admin2, intentar editar una cancha de admin1 (desde Swagger con su token).', 'Responde 403: solo puede gestionar sus propios clubes.', False,
     ['Controllers/CanchaController.php', '  esDuenoDelClub()', 'backend/app/Http/Middleware/', '  EsAdministrador.php handle()']),
])

doc.add_paragraph('3. Buscar y reservar', style='Heading 1')
tabla([
    ('3.1', 'Buscador de clubes', 'En /clubes, escribir parte del nombre o de la dirección (por ejemplo "concordia").', 'Filtra la lista mientras se escribe; sin resultados lo dice.', False,
     ['GET /api/clubs?buscar=', 'Controllers/ClubController.php index()', 'pages/clubes buscar() / cargar()']),
    ('3.2', 'Grilla de turnos', 'Abrir una cancha y moverse por la tira de días.', 'Cada turno figura libre, armándose (con los lugares de cada equipo) u ocupado; los pasados dicen "Ya pasó".', False,
     ['GET /api/turnos?cancha_id=&fecha=', 'Controllers/TurnoController.php index()', 'pages/cancha-turnos', '  buscarTurnos() / elegirDia() / yaPaso()']),
    ('3.3', 'Reservar la cancha entera', 'En un turno libre, Reservar → la cancha entera.', 'El turno pasa a ocupado y aparece en Mis reservas.', False,
     ['POST /api/reservas (tipo completa)', 'Controllers/ReservaController.php store()', 'pages/cancha-turnos', '  confirmar() / enviarReserva()']),
    ('3.4', 'Sin doble reserva', 'Con dos navegadores, dos jugadores intentan reservar el mismo turno a la vez.',
     'Uno lo consigue; el otro recibe un mensaje que dice qué turno ya está reservado (409).', False,
     ['POST /api/reservas', 'Controllers/ReservaController.php store()', '  DB::transaction + lockForUpdate()']),
    ('3.5', 'Cupo en vivo', 'Con la grilla abierta en dos navegadores, sumarse a un partido en uno.', 'El otro muestra el cupo nuevo en menos de 15 s, sin recargar.', False,
     ['pages/cancha-turnos REFRESCO_MS', '  (setInterval de 15 s)', 'pages/partidos y pages/sala-partido', '  (mismo refresco)']),
])

doc.add_paragraph('4. Matchmaking', style='Heading 1')
tabla([
    ('4.1', 'Partidos de la demo', 'Entrar a Partidos abiertos.',
     'Están el que le falta uno (9/10) y el de mitad lleno (5/10, Equipo A completo y B vacío). El completo no aparece.', True,
     ['GET /api/partidos-abiertos', 'Controllers/PartidoController.php', '  abiertos()', 'pages/partidos cargar()', 'components/tarjeta-partido']),
    ('4.2', 'Completar el que falta uno', 'Con una cuenta propia, tocar su lugar libre y confirmar en el modal.',
     'Lleva a la sala, el partido pasa a 10/10 y a "ocupado", y sale del lobby.', True,
     ['POST /api/reservas (individual)', 'Controllers/ReservaController.php store()', 'components/modal-sumarse confirmar()', 'backend/app/Jobs/', '  AvisarPartidoCompleto.php handle()']),
    ('4.3', 'Mail de partido completo', 'Revisar la casilla de esa cuenta.',
     'Llega "¡Se completó tu partido…!" con el diseño nuevo: tarjeta del partido (club, cancha, día, horario y dirección) y tu equipo resaltado en lima suave.', True,
     ['docs/n8n/partido-completo.json', '  (nodo "Armar mail")', 'backend/app/Jobs/AvisarPartidoCompleto.php', '  avisarPorN8n()']),
    ('4.4', 'Sumarse con amigos', 'En otro partido, abrir el modal y subir la cantidad.', 'Muestra el equipo, los lugares que quedan y el total a pagar; no deja pasarse del cupo.', False,
     ['POST /api/reservas (cantidad_jugadores)', 'Controllers/ReservaController.php store()', 'components/modal-sumarse', '  cambiarCantidad() / confirmar()']),
    ('4.5', 'Equipo entero', 'En el partido de mitad lleno, reservar el Equipo B entero.', 'Lo reserva y el partido queda completo; si un equipo ya tiene gente, no lo ofrece entero.', True,
     ['POST /api/reservas (tipo equipo)', 'Controllers/ReservaController.php store()', 'pages/cancha-turnos', '  elegirOpcion() / opcionDisponible()']),
    ('4.6', 'Partido completo', 'En la grilla, buscar el turno del partido completo (lo dice la consola del seed).', 'Figura ocupado y no se puede reservar ni sumarse.', True,
     ['backend/database/seeders/', '  PartidosDemoSeeder.php run()', 'Controllers/ReservaController.php store()']),
    ('4.7', 'Arrancar un partido', 'En el turno vacío, sumarse con un lugar.', 'Aparece en Partidos abiertos con 1/10 y "No estás anotado" para los demás.', True,
     ['POST /api/reservas (individual)', 'GET /api/partidos-abiertos', 'pages/cancha-turnos confirmar()']),
    ('4.8', 'Cancha entera con gente', 'En un turno con jugadores anotados, intentar reservar la cancha entera.', 'No se puede: ya hay gente anotada.', False,
     ['Controllers/ReservaController.php store()', 'pages/cancha-turnos opcionDisponible()']),
    ('4.9', 'Sala del partido', 'Abrir la sala como anotado y después con un jugador que no está anotado.',
     'El anotado ve la cancha con los dos equipos, su lugar y hasta cuándo se puede bajar; el otro recibe 403.', False,
     ['GET /api/partidos/{id}', 'Controllers/PartidoController.php show()', 'pages/sala-partido', '  cargar() / salirDelPartido()', 'components/cancha-lugares']),
    ('4.10', 'Lobby con sesión', 'En Partidos abiertos, logueado.', 'Cada tarjeta dice "Estás anotado · Equipo X" o "No estás anotado".', False,
     ['GET /api/partidos-abiertos (con token:', '  estoy_anotado, mi_equipo)', 'components/tarjeta-partido']),
    ('4.11', 'Partido incompleto', 'Dejar un partido sin completar hasta su hora, con el programador corriendo.',
     'Dentro de los 5 minutos llega el mail de partido incompleto (encabezado oscuro, con cuántos se anotaron), una sola vez.', True,
     ['backend/app/Console/Commands/', '  AvisarPartidosIncompletos.php handle()', 'backend/routes/console.php (cada 5 min)', 'backend/app/Jobs/', '  AvisarPartidoIncompleto.php handle()', 'docs/n8n/partido-incompleto.json']),
])

doc.add_paragraph('5. Mis reservas', style='Heading 1')
tabla([
    ('5.1', 'Historial', 'Entrar a Mis reservas.', 'Lista cancha, fecha, horario, tipo, equipo y estado de cada reserva.', False,
     ['GET /api/reservas', 'Controllers/ReservaController.php index()', 'pages/mis-reservas cargar()']),
    ('5.2', 'Cancelar a tiempo', 'Cancelar una reserva que todavía no empezó (aunque falten minutos).', 'Pasa a cancelada y el turno vuelve a quedar libre.', True,
     ['DELETE /api/reservas/{id}', 'Controllers/ReservaController.php destroy()', 'pages/mis-reservas', '  cancelar() / sePuedeCancelar()']),
    ('5.3', 'Cancelar un turno que ya empezó', 'Intentar cancelar una reserva cuyo turno ya empezó (desde Swagger, porque la pantalla ya no ofrece el botón).', 'No deja; el mensaje dice que se puede cancelar hasta que empieza el turno.', True,
     ['DELETE /api/reservas/{id}', 'Controllers/ReservaController.php destroy()', 'pages/mis-reservas sePuedeCancelar()']),
    ('5.4', 'Cambiar de turno', 'Cambiar una reserva a otro horario libre (se puede hasta que empieza el turno).', 'Libera el turno anterior y ocupa el nuevo; si es un partido, mantiene el equipo si entra. Con el turno ya empezado, no deja.', True,
     ['PUT /api/reservas/{id} (turno_id)', 'Controllers/ReservaController.php update()', 'pages/mis-reservas (link ?mover=)', 'pages/cancha-turnos moverAca()']),
])

doc.add_paragraph('6. Panel del administrador', style='Heading 1')
tabla([
    ('6.1', 'Día y semana', 'Como admin1, Panel → Día y Semana.', 'Muestra las reservas de sus canchas, con totales de pagadas, sin cobrar y canceladas.', False,
     ['GET /api/reservas?mis_clubes=1', 'Controllers/ReservaController.php index()', 'pages/panel-admin', '  buscar() / cambiarVista()']),
    ('6.2', 'Estado de pago', 'Cambiar una reserva a Seña y otra a Pagado.', 'Se guarda y el jugador lo ve en Mis reservas.', False,
     ['PUT /api/reservas/{id} (estado_pago)', 'Controllers/ReservaController.php update()', 'pages/panel-admin cambiarPago()']),
    ('6.3', 'El jugador no cobra', 'Como jugador, intentar cambiar el estado de pago (desde Swagger).', 'Responde 403: solo el administrador del club.', False,
     ['PUT /api/reservas/{id} (estado_pago)', 'Controllers/ReservaController.php update()']),
])

doc.add_paragraph('7. Mails y n8n', style='Heading 1')
tabla([
    ('7.1', 'Ejecuciones en n8n', 'Después de 1.1 y 4.2, mirar Executions en n8n.', 'Cada aviso figura como ejecución correcta (respuesta 200).', False,
     ['docs/n8n/*.json', 'backend/app/Jobs/AvisarUsuarioCreado.php', 'backend/app/Jobs/AvisarPartidoCompleto.php']),
    ('7.2', 'Mails en el celular', 'Abrir los mails recibidos en el celular.', 'Se leen bien a lo ancho, sin cortes; el botón lima se puede tocar.', True,
     ['docs/n8n/*.json (HTML del mail', '  en el nodo "Armar mail")']),
    ('7.3', 'Respaldo sin n8n', 'Con n8n apagado, registrarse y completar un partido.',
     'El registro funciona igual (sin bienvenida). El aviso de partido completo sale por Laravel con los colores de la app y en español ("Saludos,", no "Regards").', True,
     ['backend/app/Notifications/', '  PartidoCompleto.php toMail()', 'backend/resources/views/mail/f5gol.css', 'backend/lang/es.json']),
])

doc.add_paragraph('8. Power BI, Swagger y celular', style='Heading 1')
tabla([
    ('8.1', 'Tablero de Power BI', 'Abrir docs/powerbi/F5GOL.pbip y Actualizar.', 'Cargan las dos páginas (resumen y jugadores) con datos de las vistas bi_*.', False,
     ['docs/powerbi/F5GOL.pbip', 'backend/database/migrations/2026_11_17_', '  100000_create_vistas_power_bi.php', 'backend/database/seeders/', '  HistorialDemoSeeder.php']),
    ('8.2', 'Swagger', 'Abrir http://127.0.0.1:8000/api/documentation, hacer login y Authorize con el token.', 'Se ven todos los endpoints y los protegidos responden con el token.', False,
     ['backend/app/Swagger/OpenApi.php', 'atributos #[OA\\...] en Controllers/', 'backend/storage/api-docs/api-docs.json']),
    ('8.3', 'Pantallas en el celular', 'Recorrer clubes, grilla, partidos, sala, Mis reservas y panel a 375 px.', 'Nada se sale de la pantalla ni se pisa.', False,
     ['frontend/src/styles.css', 'pages/*/*.css (media queries)']),
])

nota('Si algo falla el día de la presentación, se presenta en la recuperación de la semana siguiente y el grupo queda regular: '
     'conviene hacer esta lista completa el día anterior y repetir la sección 0 y la 4 esa mañana (si ya se completó el partido que faltaba uno, php artisan db:seed --class=PartidosDemoSeeder lo arma de nuevo).')

os.makedirs(os.path.dirname(SALIDA), exist_ok=True)
doc.save(SALIDA)
print('guardado', SALIDA)
