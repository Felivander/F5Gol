<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

#[Fillable([
    'cancha_id',
    'fecha',
    'hora_inicio',
    'hora_fin',
    'estado',
    'cupo_maximo',
    'aviso_incompleto_at',
])]
class Turno extends Model
{
    use HasFactory;

    // Un partido de fútbol 5 completo: los dos equipos juntos
    protected $attributes = [
        'cupo_maximo' => 10,
    ];

    // Un turno pertenece a una cancha
    public function cancha()
    {
        return $this->belongsTo(Cancha::class);
    }

    // Un turno puede tener varias reservas (una completa, o varias individuales de matchmaking)
    public function reservas()
    {
        return $this->hasMany(Reserva::class);
    }

    // Fecha y hora en que empieza el partido
    public function inicio(): Carbon
    {
        return Carbon::parse(substr($this->fecha, 0, 10).' '.$this->hora_inicio);
    }

    // Vuelve a calcular si el turno está ocupado a partir de sus reservas activas:
    // queda ocupado si hay una reserva completa o si los jugadores de matchmaking llenan el cupo.
    // Un turno bloqueado por el administrador no se toca.
    public function recalcularEstado(): void
    {
        if ($this->estado === 'bloqueado') {
            return;
        }

        $hayCompleta = $this->reservas()
            ->where('tipo', 'completa')
            ->where('estado', '!=', 'cancelada')
            ->exists();
        $cupoLleno = $this->cupoActual() >= $this->cupo_maximo;

        $this->update(['estado' => $hayCompleta || $cupoLleno ? 'ocupado' : 'disponible']);
    }

    // Texto para los mensajes de la API: "del 19/11 a las 20:00 en Cancha 1"
    public function textoHorario(): string
    {
        $texto = 'del '.Carbon::parse(substr($this->fecha, 0, 10))->format('d/m').' a las '.substr($this->hora_inicio, 0, 5);
        $cancha = $this->cancha?->nombre;

        return $cancha ? "{$texto} en {$cancha}" : $texto;
    }

    // El partido ya empezó o terminó: no se puede reservar
    public function yaPaso(): bool
    {
        return $this->inicio()->isPast();
    }

    // Cantidad de jugadores anotados actualmente vía matchmaking en este turno
    public function cupoActual(): int
    {
        return (int) $this->reservas()->delPartido()->sum('cantidad_jugadores');
    }

    // ---------- Equipos del partido ----------
    // El cupo del turno es el de los dos equipos juntos; el A se queda con el lugar extra si es impar

    public function cupoEquipo(string $equipo): int
    {
        return $equipo === 'A' ? (int) ceil($this->cupo_maximo / 2) : intdiv($this->cupo_maximo, 2);
    }

    public function anotadosEquipo(string $equipo): int
    {
        return (int) $this->reservas()->delPartido()->where('equipo', $equipo)->sum('cantidad_jugadores');
    }

    public function libresEquipo(string $equipo): int
    {
        return max(0, $this->cupoEquipo($equipo) - $this->anotadosEquipo($equipo));
    }

    // Equipo con más lugares libres (si empatan, el A)
    public function equipoConMasLugares(): string
    {
        return $this->libresEquipo('B') > $this->libresEquipo('A') ? 'B' : 'A';
    }

    // "Equipo A"
    public static function nombreEquipo(string $equipo): string
    {
        return "Equipo {$equipo}";
    }
}
