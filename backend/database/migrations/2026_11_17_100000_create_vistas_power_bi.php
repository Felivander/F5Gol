<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vistas de solo lectura para el dashboard de Power BI (ver docs/powerbi/README.md).
 *
 * Juntan las tablas en filas fáciles de analizar y no exponen datos personales
 * (ni nombres ni mails de los usuarios). Usan SQL estándar, así funcionan en MySQL
 * y en el SQLite de los tests; el día de la semana, la hora y la semana se calculan
 * en Power BI.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->down();

        // Un turno por fila, con su cancha y su club
        DB::statement(<<<'SQL'
            CREATE VIEW bi_turnos AS
            SELECT
                t.id AS turno_id,
                t.fecha,
                t.hora_inicio,
                t.hora_fin,
                t.estado,
                t.cupo_maximo,
                c.id AS cancha_id,
                c.nombre AS cancha,
                c.tipo AS tipo_cancha,
                c.superficie,
                c.tarifa,
                CASE WHEN c.deleted_at IS NULL THEN 0 ELSE 1 END AS cancha_eliminada,
                cl.id AS club_id,
                cl.nombre AS club
            FROM turnos t
            JOIN canchas c ON c.id = t.cancha_id
            JOIN clubs cl ON cl.id = c.club_id
        SQL);

        // Una reserva por fila, con el turno, la cancha, el club y el monto estimado.
        // Reserva completa: la tarifa del turno. Matchmaking: la parte de cada jugador anotado.
        DB::statement(<<<'SQL'
            CREATE VIEW bi_reservas AS
            SELECT
                r.id AS reserva_id,
                r.tipo,
                r.cantidad_jugadores,
                r.estado,
                r.estado_pago,
                r.created_at AS reservada_en,
                r.usuario_id,
                u.rol AS rol_usuario,
                t.id AS turno_id,
                t.fecha,
                t.hora_inicio,
                c.id AS cancha_id,
                c.nombre AS cancha,
                c.superficie,
                cl.id AS club_id,
                cl.nombre AS club,
                c.tarifa,
                CASE
                    WHEN r.tipo = 'completa' THEN c.tarifa
                    ELSE ROUND(c.tarifa * r.cantidad_jugadores / CASE WHEN t.cupo_maximo > 0 THEN t.cupo_maximo ELSE 1 END, 2)
                END AS monto
            FROM reservas r
            JOIN turnos t ON t.id = r.turno_id
            JOIN canchas c ON c.id = t.cancha_id
            JOIN clubs cl ON cl.id = c.club_id
            JOIN users u ON u.id = r.usuario_id
        SQL);

        // Altas de usuarios, sin datos personales: rol, cómo se registró y cuándo
        DB::statement(<<<'SQL'
            CREATE VIEW bi_usuarios AS
            SELECT
                id AS usuario_id,
                rol,
                CASE WHEN google_id IS NULL THEN 'Formulario' ELSE 'Google' END AS origen,
                created_at AS creado_en
            FROM users
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS bi_usuarios');
        DB::statement('DROP VIEW IF EXISTS bi_reservas');
        DB::statement('DROP VIEW IF EXISTS bi_turnos');
    }
};
