import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { Cancha, Reserva } from '../../models';
import { Api } from '../../services/api';
import { Auth } from '../../services/auth';

interface ReservasDeCancha {
  cancha: Cancha;
  reservas: Reserva[];
}

// Fecha local en formato AAAA-MM-DD (toISOString usaría UTC y podría dar el día siguiente)
function hoy() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

@Component({
  selector: 'app-panel-admin',
  imports: [FormsModule, RouterLink],
  templateUrl: './panel-admin.html'
})
export class PanelAdmin implements OnInit {
  private api = inject(Api);
  private auth = inject(Auth);
  private router = inject(Router);

  fecha = hoy();
  reservas = signal<Reserva[]>([]);
  cargando = signal(true);
  error = signal('');

  // Reservas activas del día agrupadas por cancha y ordenadas por horario
  porCancha = computed<ReservasDeCancha[]>(() => {
    const grupos = new Map<number, ReservasDeCancha>();
    for (const reserva of this.reservas()) {
      const cancha = reserva.turno?.cancha;
      if (!cancha || reserva.estado === 'cancelada') {
        continue;
      }
      if (!grupos.has(cancha.id)) {
        grupos.set(cancha.id, { cancha, reservas: [] });
      }
      grupos.get(cancha.id)!.reservas.push(reserva);
    }
    const lista = [...grupos.values()];
    lista.forEach(g => g.reservas.sort((a, b) => a.turno!.hora_inicio.localeCompare(b.turno!.hora_inicio)));
    return lista.sort((a, b) => a.cancha.nombre.localeCompare(b.cancha.nombre));
  });

  canceladas = computed(() => this.reservas().filter(r => r.estado === 'cancelada').length);

  ngOnInit() {
    if (this.auth.usuario()?.rol !== 'administrador') {
      this.router.navigate(['/clubes']);
      return;
    }
    this.buscar();
  }

  buscar() {
    this.cargando.set(true);
    this.error.set('');
    this.api.getReservas({ fecha: this.fecha, mis_clubes: 1, por_pagina: 100 }).subscribe({
      next: res => {
        this.reservas.set(res.data);
        this.cargando.set(false);
      },
      error: () => {
        this.error.set('No se pudieron cargar las reservas del día.');
        this.cargando.set(false);
      }
    });
  }
}
