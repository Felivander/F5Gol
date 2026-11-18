# Dashboard de F5GOL en Power BI

Tablero con los indicadores del sistema, armado sobre la base MySQL de F5GOL. Cumple el requisito de la cátedra: **extracción de datos desde MySQL, modelado básico, KPIs y diseño del dashboard**.

## Qué preguntas responde

| Pregunta del dueño del club | Indicador |
|---|---|
| ¿Qué tan llenas están mis canchas? | **% Ocupación** (turnos ocupados / turnos ofrecidos), por semana, día y hora |
| ¿Cuándo tengo más demanda? | Mapa de calor **hora × día de la semana** |
| ¿Cuánto facturo y cuánto me falta cobrar? | **Facturación**, **Cobrado**, **Con seña**, **Pendiente de cobro** |
| ¿Qué cancha rinde más? | Facturación y ocupación **por cancha** |
| ¿Cuántos partidos se arman gracias al matchmaking? | **% Partidos por matchmaking** |
| ¿Se cancela mucho? | **% Cancelación** |
| ¿Crece la app? | **Usuarios nuevos** por semana y **% Altas con Google** |

## 1. Preparar los datos (una vez)

Desde `backend/`:

```bash
php artisan migrate
php artisan db:seed --class=HistorialDemoSeeder
```

- La migración crea tres **vistas** de solo lectura (no guardan datos, se calculan al consultarlas):
  - `bi_turnos`: un turno por fila, con su cancha (nombre, tipo, superficie, tarifa) y su club.
  - `bi_reservas`: una reserva por fila, con turno, cancha, club, estado de pago y **monto** estimado (la tarifa, o la parte de cada jugador en matchmaking).
  - `bi_usuarios`: altas de usuarios con rol, origen (Formulario o Google) y fecha. **Sin nombres ni mails.**
- El seeder carga **8 semanas de historial de demostración** (turnos, reservas, cancelaciones y 30 jugadores) con una semilla fija, así todos ven el mismo tablero. Si se corre `migrate:fresh --seed`, ya viene incluido.

## 2. Conectar Power BI Desktop a MySQL

1. Power BI usa el conector **MySQL Connector/NET** de Oracle. Si no está instalado, Power BI lo avisa al intentar conectar. Descargarlo de <https://dev.mysql.com/downloads/connector/net/> e instalarlo. Si con la versión 9.x Power BI sigue sin reconocerlo, probar con la 8.0.
2. Con MySQL de XAMPP andando, en Power BI: **Inicio > Obtener datos > Más... > Base de datos MySQL**.
3. Servidor: `localhost` · Base de datos: `f5gol`.
4. Credenciales: pestaña **Base de datos**, usuario `root`, contraseña vacía (la de XAMPP).
5. En el navegador elegir **solo las vistas** `bi_turnos`, `bi_reservas` y `bi_usuarios` y tocar **Cargar** (modo *Importar*).

Para actualizar los números después de usar la app: **Inicio > Actualizar**.

## 3. Modelo

1. Crear la tabla **Calendario** y las columnas calculadas copiando las definiciones de [`medidas.dax`](medidas.dax) (secciones 1 y 2).
2. En **Vista de modelo**, crear estas relaciones (todas de uno a varios, dirección simple):

   | Desde (uno) | Hacia (varios) |
   |---|---|
   | `Calendario[Date]` | `bi_turnos[fecha]` |
   | `bi_turnos[turno_id]` | `bi_reservas[turno_id]` |
   | `Calendario[Date]` | `bi_usuarios[Fecha de alta]` |

   Así un filtro de fecha o de club aplicado a los turnos también filtra sus reservas. `bi_reservas[fecha]` **no** se relaciona con Calendario: si se relacionara, habría dos caminos y Power BI no sabría cuál usar.
3. Marcar `Calendario` como tabla de fechas (**Herramientas de tabla > Marcar como tabla de fechas**).
4. Crear las medidas de las secciones 3 a 7 de [`medidas.dax`](medidas.dax). Dar formato **porcentaje** a las que empiezan con `%` y **moneda** a las de ingresos.

## 4. Diseño del tablero

Aplicar el tema con los colores de F5GOL: **Vista > Temas > Buscar temas** y elegir [`tema-f5gol.json`](tema-f5gol.json).

### Página 1 · Resumen del club

```
┌───────────────────────────────────────────────────────────────────┐
│ F5GOL · Ocupación y reservas      [Club ▾]  [Fecha: desde – hasta] │
├────────────┬────────────┬────────────┬────────────┬───────────────┤
│ % Ocupación│ Facturación│  Cobrado   │  Reservas  │ % Cancelación │
├────────────┴────────────┴──────┬─────┴────────────┴───────────────┤
│ % Ocupación por semana (línea) │ Turnos ocupados: hora × día      │
│                                │ (matriz con colores de fondo)    │
├────────────────────────────────┼──────────────────────────────────┤
│ Facturación por cancha (barras)│ Facturación por estado de pago   │
│                                │ (barras apiladas)                │
└────────────────────────────────┴──────────────────────────────────┘
```

| Objeto visual | Campos |
|---|---|
| 5 **tarjetas** | `% Ocupación`, `Facturación`, `Cobrado`, `Reservas`, `% Cancelación` |
| **Gráfico de líneas** | Eje: `Calendario[Semana]` · Valor: `% Ocupación` |
| **Matriz** (mapa de calor) | Filas: `bi_turnos[Hora]` · Columnas: `Calendario[Día de la semana]` · Valor: `Turnos ocupados` · Formato condicional > Color de fondo (escala de verdes) |
| **Barras horizontales** | Eje: `bi_turnos[cancha]` · Valor: `Facturación` · Ordenar de mayor a menor |
| **Barras apiladas 100%** | Eje: `bi_turnos[club]` · Leyenda: `bi_reservas[estado_pago]` · Valor: `Facturación` |
| **Segmentaciones** | `bi_turnos[club]` (lista desplegable) y `Calendario[Date]` (entre) |

### Página 2 · Jugadores y matchmaking

| Objeto visual | Campos |
|---|---|
| Tarjetas | `Usuarios nuevos`, `% Altas con Google`, `% Partidos por matchmaking`, `Ticket promedio` |
| **Columnas apiladas** | Eje: `Calendario[Semana]` · Leyenda: `bi_usuarios[origen]` · Valor: `Usuarios nuevos` |
| **Columnas agrupadas** | Eje: `bi_turnos[Franja]` · Valores: `Reservas completas` e `Inscripciones de matchmaking` |
| **Barras** | Eje: `bi_turnos[superficie]` · Valor: `% Ocupación` |

Consejos para que se lea bien:
- Títulos que digan la conclusión, por ejemplo *"La noche concentra la demanda"* en lugar de *"Turnos por hora"*.
- Un solo color para una sola serie; el verde de F5GOL para lo bueno y el rojo solo para cancelaciones o deuda.
- Sin gráficos de torta para más de 3 categorías: las barras se comparan mejor.

## 5. Guardar y entregar

- Guardar el archivo como `docs/powerbi/F5GOL.pbix` y commitearlo, junto con una captura de cada página (`docs/powerbi/captura-resumen.png`, `docs/powerbi/captura-jugadores.png`).
- En la presentación: mostrar el tablero, usar las segmentaciones en vivo (cambiar de club, achicar el período) y explicar de dónde sale cada número (vistas `bi_*` → modelo → medidas).

## Preguntas frecuentes

- **"No se encontró el conector de MySQL"**: falta MySQL Connector/NET (paso 2.1). Después de instalarlo, cerrar y abrir Power BI.
- **No aparecen las vistas**: correr `php artisan migrate` en `backend/`.
- **Todo da cero o vacío**: correr el seeder del paso 1, o revisar que las relaciones del paso 3 estén creadas.
- **`hora_inicio` aparece como texto**: en *Transformar datos*, cambiar el tipo de la columna a **Hora**.
