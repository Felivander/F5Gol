<?php

namespace App\Console\Commands;

use App\Models\Reserva;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Borra cuentas para volver a probar la demo: por mail, o todas las que no son del seeder
 * (las que se crearon en el ensayo con el registro o con Google). Con la cuenta se van, en
 * cascada, sus clubes, reservas e inscripciones; además borra sus tokens de sesión y vuelve a
 * dejar disponibles los turnos que quedaron incompletos.
 */
#[Signature('usuarios:borrar
    {emails?* : Mails de las cuentas a borrar}
    {--registrados : Todas las cuentas que no son del seeder (las que no terminan en @f5gol.com)}
    {--force : No pide confirmación}')]
#[Description('Borra cuentas de usuario (por mail o las creadas fuera del seeder) con sus reservas y tokens')]
class BorrarUsuarios extends Command
{
    // Las cuentas del seeder terminan en este dominio
    private const DOMINIO_SEEDER = '@f5gol.com';

    public function handle(): int
    {
        $emails = collect($this->argument('emails'))->map(fn ($e) => mb_strtolower(trim($e)))->filter()->values();

        if ($emails->isEmpty() && ! $this->option('registrados')) {
            $this->error('Decí qué cuentas borrar: uno o más mails (php artisan usuarios:borrar ana@gmail.com) o --registrados para todas las que no son del seeder.');

            return self::FAILURE;
        }

        $usuarios = User::query()
            ->when($emails->isNotEmpty(), fn ($q) => $q->whereIn('email', $emails))
            ->when($this->option('registrados'), fn ($q) => $q->where('email', 'not like', '%'.self::DOMINIO_SEEDER))
            ->withCount(['clubs', 'reservas'])
            ->orderBy('id')
            ->get();

        $noEncontrados = $emails->diff($usuarios->pluck('email')->map(fn ($e) => mb_strtolower($e)));
        foreach ($noEncontrados as $email) {
            $this->warn("No hay ninguna cuenta con el mail {$email}.");
        }

        if ($usuarios->isEmpty()) {
            $this->info('No hay cuentas para borrar.');

            return self::SUCCESS;
        }

        $this->table(
            ['Mail', 'Nombre', 'Rol', 'Clubes', 'Reservas'],
            $usuarios->map(fn (User $u) => [$u->email, $u->name, $u->rol, $u->clubs_count, $u->reservas_count])
        );

        if (! $this->option('force') && ! $this->confirm("Se van a borrar {$usuarios->count()} cuentas con sus clubes, reservas e inscripciones. ¿Seguís?")) {
            $this->info('No se borró nada.');

            return self::SUCCESS;
        }

        $liberados = DB::transaction(function () use ($usuarios) {
            $ids = $usuarios->pluck('id');
            $turnos = Reserva::whereIn('usuario_id', $ids)->pluck('turno_id')->unique();

            // Los tokens de Sanctum no tienen clave foránea: se borran a mano
            DB::table('personal_access_tokens')->where('tokenable_type', User::class)->whereIn('tokenable_id', $ids)->delete();
            // Sus clubes, canchas, reservas e inscripciones se borran en cascada
            User::whereIn('id', $ids)->delete();

            return $this->liberarTurnos($turnos);
        });

        $this->info("Se borraron {$usuarios->count()} cuentas.".($liberados ? " Quedaron disponibles {$liberados} turnos que estaban ocupados." : ''));

        return self::SUCCESS;
    }

    // Un turno ocupado vuelve a estar disponible si ya no tiene una reserva entera ni el cupo lleno
    private function liberarTurnos($turnoIds): int
    {
        $liberados = 0;

        Turno::whereIn('id', $turnoIds)->where('estado', 'ocupado')->get()->each(function (Turno $turno) use (&$liberados) {
            $reservadoEntero = $turno->reservas()->where('tipo', 'completa')->where('estado', '!=', 'cancelada')->exists();

            if (! $reservadoEntero && $turno->cupoActual() < $turno->cupo_maximo) {
                $turno->update(['estado' => 'disponible']);
                $liberados++;
            }
        });

        return $liberados;
    }
}
