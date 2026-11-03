import { Component, OnInit, inject, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { Reserva } from '../../models';
import { Api } from '../../services/api';
import { Auth } from '../../services/auth';

@Component({
  selector: 'app-mis-reservas',
  imports: [RouterLink],
  templateUrl: './mis-reservas.html'
})
export class MisReservas implements OnInit {
  private api = inject(Api);
  private auth = inject(Auth);
  private router = inject(Router);

  reservas = signal<Reserva[]>([]);
  pagina = signal(1);
  ultimaPagina = signal(1);
  cargando = signal(true);
  error = signal('');
  aviso = signal('');
  cancelando = signal<number | null>(null);

  ngOnInit() {
    if (!this.auth.logueado()) {
      this.router.navigate(['/login']);
      return;
    }
    this.cargar(1);
  }

  cargar(pagina: number) {
    this.cargando.set(true);
    this.api.getReservas({ usuario_id: this.auth.usuario()!.id, page: pagina }).subscribe({
      next: res => {
        this.reservas.set(res.data);
        this.pagina.set(res.current_page);
        this.ultimaPagina.set(res.last_page);
        this.cargando.set(false);
      },
      error: () => {
        this.error.set('No se pudieron cargar tus reservas.');
        this.cargando.set(false);
      }
    });
  }

  // Solo se ofrece cancelar lo que todavía no se jugó
  sePuedeCancelar(reserva: Reserva) {
    if (reserva.estado === 'cancelada' || !reserva.turno) {
      return false;
    }
    const inicio = new Date(`${reserva.turno.fecha.slice(0, 10)}T${reserva.turno.hora_inicio}`);
    return inicio.getTime() > Date.now();
  }

  cancelar(reserva: Reserva) {
    if (!confirm('¿Querés cancelar esta reserva?')) {
      return;
    }
    this.cancelando.set(reserva.id);
    this.error.set('');
    this.aviso.set('');
    this.api.cancelarReserva(reserva.id).subscribe({
      next: res => {
        this.aviso.set(res.message);
        this.cancelando.set(null);
        this.cargar(this.pagina());
      },
      error: err => {
        // 409: fuera del plazo de cancelación o ya cancelada
        this.error.set(err.error?.message ?? 'No se pudo cancelar la reserva.');
        this.cancelando.set(null);
      }
    });
  }
}
