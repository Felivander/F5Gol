import { Component, DestroyRef, OnInit, inject, input, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { Cancha, CupoEquipo, Equipo, Equipos, NuevaReserva, Turno } from '../../models';
import { Api } from '../../services/api';
import { Auth } from '../../services/auth';

import { CanchaDiagrama } from '../../components/cancha-diagrama/cancha-diagrama';
import { Icono } from '../../components/icono/icono';
import { fechaRelativa, partesFecha, pesos, textoTipo } from '../../formato';
import { textoSuperficie } from '../../models';

const REFRESCO_MS = 15_000;

// Formas de entrar a un turno: un lugar suelto, un equipo entero o la cancha entera
type Opcion = 'lugar' | 'equipo' | 'cancha';

// Fecha de hoy en formato AAAA-MM-DD, en la zona horaria del usuario
function hoy(): string {
  const ahora = new Date();
  return `${ahora.getFullYear()}-${String(ahora.getMonth() + 1).padStart(2, '0')}-${String(ahora.getDate()).padStart(2, '0')}`;
}

@Component({
  selector: 'app-cancha-turnos',
  imports: [RouterLink, FormsModule, CanchaDiagrama, Icono],
  templateUrl: './cancha-turnos.html',
  styleUrl: './cancha-turnos.css'
})
export class CanchaTurnos implements OnInit {
  private api = inject(Api);
  private router = inject(Router);
  protected auth = inject(Auth);

  // Viene del parámetro de ruta /canchas/:id
  id = input.required<string>();
  // ?mover=<id de reserva>: el jugador viene de Mis reservas a elegir un turno nuevo
  mover = input<string>();
  // ?dia=AAAA-MM-DD: se llega desde un partido abierto y se muestra ese día
  dia = input<string>();

  protected fechaRelativa = fechaRelativa;
  protected pesos = pesos;
  protected textoTipo = textoTipo;
  protected textoSuperficie = textoSuperficie;

  cancha = signal<Cancha | null>(null);
  turnos = signal<Turno[]>([]);
  // Arranca en el día de hoy: con varias semanas de turnos cargados, "todas las fechas" es una lista muy larga
  fecha = hoy();
  cargando = signal(true);
  error = signal('');
  aviso = signal('');
  // Después de sumarse a un partido, el turno para abrir su sala
  salaTurno = signal<number | null>(null);
  // Turno que se está reservando, para deshabilitar su botón mientras espera la API
  reservando = signal<number | null>(null);

  constructor() {
    // Cupo "en tiempo real": se vuelve a pedir la grilla cada 15 segundos mientras la página está abierta
    const refresco = setInterval(() => {
      if (this.reservando() === null && !document.hidden) {
        this.buscarTurnos(true);
      }
    }, REFRESCO_MS);
    inject(DestroyRef).onDestroy(() => clearInterval(refresco));
  }

  // El administrador dueño del club puede bloquear y desbloquear turnos
  esDueno() {
    const usuario = this.auth.usuario();
    return !!usuario && usuario.id === this.cancha()?.club?.administrador_id;
  }

  cambiarBloqueo(turno: Turno) {
    const bloquear = turno.estado !== 'bloqueado';
    this.reservando.set(turno.id);
    this.error.set('');
    this.aviso.set('');
    this.api.bloquearTurno(turno.id, bloquear).subscribe({
      next: res => {
        this.aviso.set(res.message);
        this.reservando.set(null);
        this.buscarTurnos();
      },
      error: err => {
        this.error.set(err.error?.message ?? 'No se pudo cambiar el bloqueo del turno.');
        this.reservando.set(null);
      }
    });
  }

  eliminarTurno(turno: Turno) {
    if (!confirm(`¿Eliminar el turno del ${turno.fecha.slice(0, 10)} a las ${turno.hora_inicio.slice(0, 5)}?`)) {
      return;
    }
    this.reservando.set(turno.id);
    this.error.set('');
    this.aviso.set('');
    this.api.eliminarTurno(turno.id).subscribe({
      next: res => {
        this.aviso.set(res.message);
        this.reservando.set(null);
        this.buscarTurnos();
      },
      error: err => {
        // 409: alguien reservó mientras tanto
        this.error.set(err.error?.message ?? 'No se pudo eliminar el turno.');
        this.reservando.set(null);
        this.buscarTurnos();
      }
    });
  }

  // Se puede reservar entero solo si nadie se anotó por matchmaking
  puedeReservar(turno: Turno) {
    return turno.estado === 'disponible' && turno.cupo_actual === 0 && !this.yaPaso(turno);
  }

  // Los turnos que ya empezaron se muestran, pero sin botones (la API respondería 409)
  yaPaso(turno: Turno) {
    return new Date(`${turno.fecha.slice(0, 10)}T${turno.hora_inicio}`).getTime() <= Date.now();
  }

  // Los próximos 7 días, para elegir con un toque en el celular
  protected dias = Array.from({ length: 7 }, (_, i) => {
    const d = new Date();
    d.setDate(d.getDate() + i);
    const valor = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    return { valor, ...partesFecha(valor), titulo: i === 0 ? 'Hoy' : i === 1 ? 'Mañana' : '' };
  });

  esDiaDeLaTira() {
    return this.dias.some(d => d.valor === this.fecha);
  }

  elegirDia(valor: string) {
    this.fecha = valor;
    this.buscarTurnos();
  }

  // ---------- Equipos y formas de entrar al partido ----------

  // Lugares de cada equipo (si la API no los manda, se reparte el cupo en dos)
  equipos(turno: Turno): Equipos {
    if (turno.equipos) {
      return turno.equipos;
    }
    const a = Math.ceil(turno.cupo_maximo / 2);
    return {
      A: { cupo: a, anotados: 0, faltan: a },
      B: { cupo: turno.cupo_maximo - a, anotados: 0, faltan: turno.cupo_maximo - a }
    };
  }

  // Un punto por lugar del equipo: lleno si ya está ocupado
  puntos(cupo: CupoEquipo) {
    return Array.from({ length: cupo.cupo }, (_, i) => i < cupo.anotados);
  }

  // Lugares libres sumando los dos equipos
  lugaresLibres(turno: Turno) {
    return turno.cupo_maximo - turno.cupo_actual;
  }

  // Equipos que se pueden reservar enteros (los que no tienen a nadie)
  equiposVacios(turno: Turno): Equipo[] {
    const e = this.equipos(turno);
    return (['A', 'B'] as Equipo[]).filter(x => e[x].anotados === 0);
  }

  // Equipo con más lugares libres (si empatan, el A)
  private equipoSugerido(turno: Turno): Equipo {
    const e = this.equipos(turno);
    return e.B.faltan > e.A.faltan ? 'B' : 'A';
  }

  // Panel de opciones abierto (uno por vez) y lo que el jugador va eligiendo
  abierto = signal<number | null>(null);
  opcion = signal<Opcion>('lugar');
  equipoElegido = signal<Equipo>('A');

  abrirOpciones(turno: Turno) {
    if (!this.auth.usuario()) {
      this.router.navigate(['/login']);
      return;
    }
    if (this.abierto() === turno.id) {
      this.abierto.set(null);
      return;
    }
    this.abierto.set(turno.id);
    this.opcion.set('lugar');
    this.equipoElegido.set(this.equipoSugerido(turno));
    this.error.set('');
  }

  elegirOpcion(turno: Turno, opcion: Opcion) {
    this.opcion.set(opcion);
    if (opcion === 'equipo') {
      const vacios = this.equiposVacios(turno);
      if (!vacios.includes(this.equipoElegido())) {
        this.equipoElegido.set(vacios[0] ?? 'A');
      }
    } else if (opcion === 'lugar' && this.equipos(turno)[this.equipoElegido()].faltan === 0) {
      this.equipoElegido.set(this.equipoSugerido(turno));
    }
  }

  // Si la opción elegida se puede usar en ese turno
  opcionDisponible(turno: Turno, opcion: Opcion): boolean {
    switch (opcion) {
      case 'lugar':
        return this.lugaresLibres(turno) > 0;
      case 'equipo':
        return this.equiposVacios(turno).length > 0;
      case 'cancha':
        return turno.cupo_actual === 0;
    }
  }

  // Si el equipo se puede elegir para la opción actual
  equipoDisponible(turno: Turno, equipo: Equipo): boolean {
    const e = this.equipos(turno)[equipo];
    return this.opcion() === 'equipo' ? e.anotados === 0 : e.faltan > 0;
  }

  precioOpcion(turno: Turno, opcion: Opcion): string {
    const tarifa = Number(this.cancha()?.tarifa ?? 0);
    switch (opcion) {
      case 'lugar':
        return pesos(tarifa / Math.max(1, turno.cupo_maximo));
      case 'equipo':
        return pesos(tarifa / 2);
      case 'cancha':
        return pesos(tarifa);
    }
  }

  confirmar(turno: Turno) {
    const opcion = this.opcion();
    if (!this.opcionDisponible(turno, opcion)) {
      return;
    }
    const datos: Pick<NuevaReserva, 'tipo' | 'equipo'> = opcion === 'cancha'
      ? { tipo: 'completa' }
      : { tipo: opcion === 'equipo' ? 'equipo' : 'individual', equipo: this.equipoElegido() };
    this.enviarReserva(turno, datos);
  }

  moverAca(turno: Turno) {
    this.reservando.set(turno.id);
    this.error.set('');
    this.api.actualizarReserva(Number(this.mover()), { turno_id: turno.id }).subscribe({
      next: () => this.router.navigate(['/mis-reservas'], { state: { aviso: 'Listo, tu reserva pasó al turno nuevo.' } }),
      error: err => {
        // 409: el turno nuevo se ocupó, ya pasó o el original está fuera de plazo
        this.error.set(err.error?.message ?? 'No se pudo cambiar el turno.');
        this.reservando.set(null);
        this.buscarTurnos();
      }
    });
  }

  private enviarReserva(turno: Turno, datos: Pick<NuevaReserva, 'tipo' | 'equipo'>) {
    const usuario = this.auth.usuario();
    if (!usuario) {
      this.router.navigate(['/login']);
      return;
    }

    this.reservando.set(turno.id);
    this.error.set('');
    this.aviso.set('');
    this.salaTurno.set(null);
    this.api.crearReserva({ turno_id: turno.id, usuario_id: usuario.id, cantidad_jugadores: 1, ...datos }).subscribe({
      next: res => {
        // El mensaje de la API ya dice qué se reservó, en qué equipo y cuánto falta
        this.aviso.set(res.message);
        this.salaTurno.set(datos.tipo === 'completa' ? null : turno.id);
        this.abierto.set(null);
        this.reservando.set(null);
        this.buscarTurnos();
      },
      error: err => {
        // 409: el turno o el equipo se ocupó mientras tanto, o ya pasó
        this.error.set(err.error?.message ?? 'No se pudo hacer la reserva.');
        this.reservando.set(null);
        this.buscarTurnos();
      }
    });
  }

  ngOnInit() {
    if (this.dia() && /^\d{4}-\d{2}-\d{2}/.test(this.dia()!)) {
      this.fecha = this.dia()!.slice(0, 10);
    }
    this.api.getCancha(Number(this.id())).subscribe({
      next: cancha => this.cancha.set(cancha),
      error: () => this.error.set('No se encontró la cancha.')
    });
    this.buscarTurnos();
  }

  // silencioso: refresco automático, sin mostrar "Buscando turnos..." ni pisar errores
  buscarTurnos(silencioso = false) {
    if (!silencioso) {
      this.cargando.set(true);
    }
    this.api.getTurnos(Number(this.id()), this.fecha).subscribe({
      next: turnos => {
        // La API ordena solo por hora; acá ordenamos por fecha y hora
        const orden = (t: Turno) => t.fecha + t.hora_inicio;
        this.turnos.set([...turnos].sort((a, b) => orden(a).localeCompare(orden(b))));
        this.cargando.set(false);
      },
      error: () => {
        if (!silencioso) {
          this.error.set('No se pudieron cargar los turnos.');
        }
        this.cargando.set(false);
      }
    });
  }
}
