<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerarTurnosRequest;
use App\Http\Requests\TurnoRequest;
use App\Models\Cancha;
use App\Models\Turno;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class TurnoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * Grilla de disponibilidad: /api/turnos?cancha_id=1&fecha=2026-09-10
     */
    #[OA\Get(
        path: '/api/turnos',
        summary: 'Listar turnos',
        tags: ['Turnos'],
        parameters: [
            new OA\Parameter(name: 'cancha_id', description: 'Filtrar por cancha', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'fecha', description: 'Filtrar por fecha (YYYY-MM-DD)', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Listado obtenido correctamente.'),
        ]
    )]
    public function index(Request $request)
    {
        $query = Turno::with('cancha:id,nombre,club_id')->orderBy('hora_inicio');

        if ($request->has('cancha_id')) {
            $query->where('cancha_id', $request->query('cancha_id'));
        }

        if ($request->has('fecha')) {
            $query->where('fecha', $request->query('fecha'));
        }

        $turnos = $query->get()->map(function (Turno $turno) {
            $turno->cupo_actual = $turno->cupoActual();
            // Lugares de cada equipo, para elegir dónde sumarse o reservar un equipo entero
            $turno->equipos = collect(['A', 'B'])->mapWithKeys(fn ($e) => [$e => [
                'cupo' => $turno->cupoEquipo($e),
                'anotados' => $turno->anotadosEquipo($e),
                'faltan' => $turno->libresEquipo($e),
            ]]);

            return $turno;
        });

        return response()->json($turnos, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    #[OA\Post(
        path: '/api/turnos',
        summary: 'Crear turno',
        tags: ['Turnos'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/TurnoInput')),
        responses: [
            new OA\Response(response: 201, description: 'Creado correctamente.'),
            new OA\Response(response: 422, description: 'Datos inválidos: devuelve un mensaje por cada campo con error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'El usuario no es administrador o la cancha no es de su club.'),
        ]
    )]
    public function store(TurnoRequest $request)
    {
        if (! $this->esDuenoDeLaCancha($request, $request->input('cancha_id'))) {
            return $this->respuestaNoEsDueno();
        }

        $turno = Turno::create($request->validated());

        return response()->json([
            'message' => 'El turno '.$turno->textoHorario().' se creó correctamente.',
            'turno' => $turno,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    #[OA\Get(
        path: '/api/turnos/{id}',
        summary: 'Obtener turno',
        tags: ['Turnos'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/id')],
        responses: [
            new OA\Response(response: 200, description: 'Operación exitosa.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
        ]
    )]
    public function show(string $id)
    {
        $turno = Turno::with('cancha')->findOrFail($id);
        $turno->cupo_actual = $turno->cupoActual();

        return response()->json($turno, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    #[OA\Put(
        path: '/api/turnos/{id}',
        summary: 'Actualizar turno',
        tags: ['Turnos'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/id')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/TurnoInput')),
        responses: [
            new OA\Response(response: 200, description: 'Actualizado correctamente.'),
            new OA\Response(response: 422, description: 'Datos inválidos: devuelve un mensaje por cada campo con error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'El usuario no es administrador o la cancha no es de su club.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
        ]
    )]
    public function update(TurnoRequest $request, string $id)
    {
        $turno = Turno::find($id);

        if (! $turno) {
            return response()->json([
                'message' => "No existe un turno con el id {$id}.",
            ], 404);
        }

        if (! $this->esDuenoDeLaCancha($request, $turno->cancha_id)
            || ($request->has('cancha_id') && ! $this->esDuenoDeLaCancha($request, $request->input('cancha_id')))) {
            return $this->respuestaNoEsDueno();
        }

        $turno->update($request->validated());

        return response()->json([
            'message' => 'Los cambios del turno '.$turno->fresh()->textoHorario().' se guardaron.',
            'turno' => $turno->fresh(),
        ], 200);
    }

    #[OA\Patch(
        path: '/api/turnos/{id}/bloqueo',
        summary: 'Bloquear o desbloquear un turno',
        description: 'Bloquea un turno libre (por ejemplo, por mantenimiento) para que no se pueda reservar. Al desbloquearlo vuelve a calcularse su estado.',
        tags: ['Turnos'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/id')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['bloqueado'],
            properties: [new OA\Property(property: 'bloqueado', type: 'boolean', example: true)]
        )),
        responses: [
            new OA\Response(response: 200, description: 'Estado del turno actualizado.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'El usuario no es administrador o la cancha no es de su club.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
            new OA\Response(response: 409, description: 'El turno tiene reservas activas.'),
        ]
    )]
    public function bloqueo(Request $request, string $id)
    {
        $request->validate(['bloqueado' => ['required', 'boolean']], [
            'bloqueado.required' => 'Indicá si el turno queda bloqueado (true) o no (false).',
            'bloqueado.boolean' => 'El campo bloqueado tiene que ser true (bloquear) o false (desbloquear).',
        ]);

        $turno = Turno::with('cancha.club')->find($id);

        if (! $turno) {
            return response()->json([
                'message' => "No existe un turno con el id {$id}.",
            ], 404);
        }

        if ($turno->cancha?->club?->administrador_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Solo podés bloquear turnos de las canchas de tus propios clubes.',
            ], 403);
        }

        if ($request->boolean('bloqueado')) {
            $tieneReservas = $turno->reservas()->where('estado', '!=', 'cancelada')->exists();
            if ($tieneReservas) {
                return response()->json([
                    'message' => 'El turno tiene reservas activas: cancelalas antes de bloquearlo.',
                ], 409);
            }

            $turno->update(['estado' => 'bloqueado']);
        } else {
            // Se saca el bloqueo y el estado vuelve a salir de sus reservas
            $turno->update(['estado' => 'disponible']);
            $turno->recalcularEstado();
        }

        return response()->json([
            'message' => $request->boolean('bloqueado')
                ? 'El turno '.$turno->textoHorario().' quedó bloqueado por mantenimiento: nadie lo puede reservar.'
                : 'El turno '.$turno->textoHorario().' se desbloqueó y se puede volver a reservar.',
            'turno' => $turno->fresh(),
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    #[OA\Delete(
        path: '/api/turnos/{id}',
        summary: 'Eliminar turno',
        description: 'Solo se puede eliminar un turno sin reservas activas.',
        tags: ['Turnos'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/id')],
        responses: [
            new OA\Response(response: 200, description: 'Eliminado correctamente.', content: new OA\JsonContent(ref: '#/components/schemas/Mensaje')),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'El usuario no es administrador o la cancha no es de su club.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
            new OA\Response(response: 409, description: 'El turno tiene reservas activas.'),
        ]
    )]
    public function destroy(Request $request, string $id)
    {
        $turno = Turno::find($id);

        if (! $turno) {
            return response()->json([
                'message' => "No existe un turno con el id {$id}.",
            ], 404);
        }

        if (! $this->esDuenoDeLaCancha($request, $turno->cancha_id)) {
            return $this->respuestaNoEsDueno();
        }

        if ($turno->reservas()->where('estado', '!=', 'cancelada')->exists()) {
            return response()->json([
                'message' => 'El turno tiene reservas activas: cancelalas antes de eliminarlo.',
            ], 409);
        }

        $turno->delete();

        return response()->json([
            'message' => 'El turno '.$turno->textoHorario().' se eliminó.',
        ], 200);
    }

    #[OA\Post(
        path: '/api/turnos/generar',
        summary: 'Generar turnos en lote',
        description: 'Crea los turnos de una cancha para un rango de fechas (hasta 90 días), en los días de la semana elegidos y dentro de una franja horaria, cada uno de la duración indicada. No pisa turnos que ya existen ni que se superponen, y no crea turnos que ya empezaron.',
        tags: ['Turnos'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['cancha_id', 'desde', 'hasta', 'hora_desde', 'hora_hasta', 'duracion'],
            properties: [
                new OA\Property(property: 'cancha_id', type: 'integer', example: 1),
                new OA\Property(property: 'desde', type: 'string', format: 'date', example: '2026-12-01'),
                new OA\Property(property: 'hasta', type: 'string', format: 'date', example: '2026-12-31'),
                new OA\Property(property: 'dias', description: '0 = domingo ... 6 = sábado. Si no se manda, todos los días.', type: 'array', items: new OA\Items(type: 'integer', minimum: 0, maximum: 6), example: [1, 2, 3, 4, 5]),
                new OA\Property(property: 'hora_desde', type: 'string', example: '15:00'),
                new OA\Property(property: 'hora_hasta', description: 'El último turno termina a esta hora o antes', type: 'string', example: '23:00'),
                new OA\Property(property: 'duracion', description: 'Minutos de cada turno', type: 'integer', enum: [30, 60, 90, 120], example: 60),
                new OA\Property(property: 'cupo_maximo', description: 'Lugares del partido de matchmaking, sumando los dos equipos. Por defecto, la capacidad de la cancha.', type: 'integer', nullable: true, example: 10),
            ]
        )),
        responses: [
            new OA\Response(response: 201, description: 'Turnos generados.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Se crearon 40 turnos.'),
                new OA\Property(property: 'creados', type: 'integer', example: 40),
                new OA\Property(property: 'omitidos', description: 'Ya existían, se superponían con otro turno o ya empezaron', type: 'integer', example: 2),
            ])),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'El usuario no es administrador o la cancha no es de su club.'),
            new OA\Response(response: 422, description: 'Datos inválidos: devuelve un mensaje por cada campo con error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ]
    )]
    public function generar(GenerarTurnosRequest $request)
    {
        $datos = $request->validated();

        if (! $this->esDuenoDeLaCancha($request, $datos['cancha_id'])) {
            return $this->respuestaNoEsDueno();
        }

        $cancha = Cancha::findOrFail($datos['cancha_id']);
        // El cupo del partido es el de los dos equipos juntos: la capacidad de la cancha
        $cupo = $datos['cupo_maximo'] ?? max(2, $cancha->capacidad_jugadores);
        $dias = isset($datos['dias']) ? array_map('intval', $datos['dias']) : null;
        $duracion = (int) $datos['duracion'];

        // Turnos que ya tiene la cancha en el rango, agrupados por fecha, para no superponer
        $existentes = Turno::where('cancha_id', $cancha->id)
            ->whereBetween('fecha', [$datos['desde'], $datos['hasta']])
            ->get(['fecha', 'hora_inicio', 'hora_fin'])
            ->groupBy(fn (Turno $t) => substr($t->fecha, 0, 10));

        $nuevos = [];
        $omitidos = 0;
        $ahora = now();
        $ultimoDia = Carbon::parse($datos['hasta']);

        for ($fecha = Carbon::parse($datos['desde']); $fecha->lte($ultimoDia); $fecha->addDay()) {
            if ($dias !== null && ! in_array($fecha->dayOfWeek, $dias, true)) {
                continue;
            }

            $dia = $fecha->toDateString();
            $inicio = Carbon::parse("{$dia} {$datos['hora_desde']}");
            $limite = Carbon::parse("{$dia} {$datos['hora_hasta']}");
            $delDia = collect($existentes->get($dia, []));

            while ($inicio->copy()->addMinutes($duracion)->lte($limite)) {
                $fin = $inicio->copy()->addMinutes($duracion);
                $horaInicio = $inicio->format('H:i:s');
                $horaFin = $fin->format('H:i:s');

                // Se compara en HH:MM: según la base, la hora viene como "20:00" o "20:00:00"
                $seSuperpone = $delDia->contains(
                    fn (Turno $t) => substr($t->hora_inicio, 0, 5) < $fin->format('H:i')
                        && substr($t->hora_fin, 0, 5) > $inicio->format('H:i')
                );

                if ($seSuperpone || $inicio->lte($ahora)) {
                    $omitidos++;
                } else {
                    $nuevos[] = [
                        'cancha_id' => $cancha->id,
                        'fecha' => $dia,
                        'hora_inicio' => $horaInicio,
                        'hora_fin' => $horaFin,
                        'estado' => 'disponible',
                        'cupo_maximo' => $cupo,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ];
                }

                $inicio = $fin;
            }
        }

        DB::transaction(function () use ($nuevos) {
            foreach (array_chunk($nuevos, 200) as $tanda) {
                Turno::insert($tanda);
            }
        });

        $creados = count($nuevos);

        return response()->json([
            'message' => $creados === 1 ? 'Se creó 1 turno.' : "Se crearon {$creados} turnos.",
            'creados' => $creados,
            'omitidos' => $omitidos,
        ], 201);
    }

    // Un administrador solo gestiona los turnos de las canchas de sus clubes
    private function esDuenoDeLaCancha(Request $request, mixed $canchaId): bool
    {
        return Cancha::where('id', $canchaId)
            ->whereHas('club', fn ($q) => $q->where('administrador_id', $request->user()->id))
            ->exists();
    }

    private function respuestaNoEsDueno()
    {
        return response()->json([
            'message' => 'Solo podés gestionar los turnos de las canchas de tus propios clubes.',
        ], 403);
    }
}
