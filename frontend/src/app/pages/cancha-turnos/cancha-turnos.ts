import { Component, OnInit, inject, input, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { Cancha, Turno } from '../../models';
import { Api } from '../../services/api';
import { Auth } from '../../services/auth';

import { CanchaDiagrama } from '../../components/cancha-diagrama/cancha-diagrama';

@Component({
  selector: 'app-cancha-turnos',
  imports: [RouterLink, FormsModule, CanchaDiagrama],
  templateUrl: './cancha-turnos.html'
})
export class CanchaTurnos implements OnInit {
  private api = inject(Api);
  private router = inject(Router);
  protected auth = inject(Auth);

  // Viene del parámetro de ruta /canchas/:id
  id = input.required<string>();

  cancha = signal<Cancha | null>(null);
  turnos = signal<Turno[]>([]);
  fecha = '';
  cargando = signal(true);
  error = signal('');
  aviso = signal('');
  // Turno que se está reservando, para deshabilitar su botón mientras espera la API
  reservando = signal<number | null>(null);

  // Se puede reservar entero solo si nadie se anotó por matchmaking
  puedeReservar(turno: Turno) {
    return turno.estado === 'disponible' && turno.cupo_actual === 0;
  }

  reservar(turno: Turno) {
    const usuario = this.auth.usuario();
    if (!usuario) {
      this.router.navigate(['/login']);
      return;
    }

    this.reservando.set(turno.id);
    this.error.set('');
    this.aviso.set('');
    this.api.crearReserva({ turno_id: turno.id, usuario_id: usuario.id, tipo: 'completa' }).subscribe({
      next: () => {
        this.aviso.set(`¡Listo! Reservaste el turno de las ${turno.hora_inicio.slice(0, 5)} del ${turno.fecha}.`);
        this.reservando.set(null);
        this.buscarTurnos();
      },
      error: err => {
        // 409: el turno se ocupó mientras tanto o ya pasó
        this.error.set(err.error?.message ?? 'No se pudo hacer la reserva.');
        this.reservando.set(null);
        this.buscarTurnos();
      }
    });
  }

  ngOnInit() {
    this.api.getCancha(Number(this.id())).subscribe({
      next: cancha => this.cancha.set(cancha),
      error: () => this.error.set('No se encontró la cancha.')
    });
    this.buscarTurnos();
  }

  buscarTurnos() {
    this.cargando.set(true);
    this.api.getTurnos(Number(this.id()), this.fecha).subscribe({
      next: turnos => {
        // La API ordena solo por hora; acá ordenamos por fecha y hora
        const orden = (t: Turno) => t.fecha + t.hora_inicio;
        this.turnos.set([...turnos].sort((a, b) => orden(a).localeCompare(orden(b))));
        this.cargando.set(false);
      },
      error: () => {
        this.error.set('No se pudieron cargar los turnos.');
        this.cargando.set(false);
      }
    });
  }
}
