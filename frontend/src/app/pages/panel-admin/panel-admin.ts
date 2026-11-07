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
function formatear(d: Date) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function hoy() {
  return formatear(new Date());
}

// Lunes y domingo de la semana de una fecha AAAA-MM-DD
function semanaDe(fecha: string) {
  const d = new Date(`${fecha}T12:00:00`);
  const lunes = new Date(d);
  lunes.setDate(d.getDate() - ((d.getDay() + 6) % 7));
  const domingo = new Date(lunes);
  domingo.setDate(lunes.getDate() + 6);
  return { desde: formatear(lunes), hasta: formatear(domingo) };
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
  vista = signal<'dia' | 'semana'>('dia');
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
    const orden = (r: Reserva) => r.turno!.fecha.slice(0, 10) + r.turno!.hora_inicio;
    lista.forEach(g => g.reservas.sort((a, b) => orden(a).localeCompare(orden(b))));
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

  cambiarPago(reserva: Reserva, estadoPago: Reserva['estado_pago']) {
    this.error.set('');
    this.api.actualizarReserva(reserva.id, { estado_pago: estadoPago }).subscribe({
      next: res => this.reservas.update(lista => lista.map(r => r.id === reserva.id ? { ...r, estado_pago: res.reserva.estado_pago } : r)),
      error: err => {
        this.error.set(err.error?.message ?? 'No se pudo actualizar el pago.');
        this.buscar();
      }
    });
  }

  // "lunes 3/11 al domingo 9/11" para el título de la vista semanal
  textoSemana() {
    const { desde, hasta } = semanaDe(this.fecha);
    const corto = (f: string) => `${Number(f.slice(8, 10))}/${Number(f.slice(5, 7))}`;
    return `del lunes ${corto(desde)} al domingo ${corto(hasta)}`;
  }

  // "Lun 3/11"
  nombreDia(fecha: string) {
    const d = new Date(`${fecha.slice(0, 10)}T12:00:00`);
    const dia = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'][d.getDay()];
    return `${dia} ${d.getDate()}/${d.getMonth() + 1}`;
  }

  cambiarVista(vista: 'dia' | 'semana') {
    this.vista.set(vista);
    this.buscar();
  }

  buscar() {
    this.cargando.set(true);
    this.error.set('');
    const { desde, hasta } = semanaDe(this.fecha);
    const rango: Record<string, string> = this.vista() === 'dia' ? { fecha: this.fecha } : { desde, hasta };
    this.api.getReservas({ ...rango, mis_clubes: 1, por_pagina: 100 }).subscribe({
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
