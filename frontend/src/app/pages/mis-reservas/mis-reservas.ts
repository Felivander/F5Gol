import { Component, OnInit, inject, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { Icono } from '../../components/icono/icono';
import { fechaRelativa, partesFecha } from '../../formato';
import { Reserva } from '../../models';
import { Api } from '../../services/api';
import { Dialogos } from '../../services/dialogos';
import { Auth } from '../../services/auth';

@Component({
  selector: 'app-mis-reservas',
  imports: [RouterLink, Icono],
  templateUrl: './mis-reservas.html',
  styleUrl: './mis-reservas.css'
})
export class MisReservas implements OnInit {
  private api = inject(Api);
  private dialogos = inject(Dialogos);
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
    // Mensaje que deja la grilla después de mover una reserva de turno
    const avisoAnterior = history.state?.aviso;
    if (avisoAnterior) {
      this.aviso.set(avisoAnterior);
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

  protected partesFecha = partesFecha;

  // Solo se ofrece cancelar lo que todavía no se jugó
  sePuedeCancelar(reserva: Reserva) {
    if (reserva.estado === 'cancelada' || !reserva.turno) {
      return false;
    }
    const inicio = new Date(`${reserva.turno.fecha.slice(0, 10)}T${reserva.turno.hora_inicio}`);
    return inicio.getTime() > Date.now();
  }

  async cancelar(reserva: Reserva) {
    const turno = reserva.turno;
    const cuando = turno
      ? ` del ${fechaRelativa(turno.fecha).toLowerCase()} a las ${turno.hora_inicio.slice(0, 5)}${turno.cancha ? ' en ' + turno.cancha.nombre : ''}`
      : '';
    if (!(await this.dialogos.confirmar({
      titulo: `¿Cancelar la reserva${cuando}?`,
      mensaje: reserva.tipo === 'completa'
        ? 'La cancha queda libre para que la reserve otro.'
        : 'Tu lugar en el partido queda libre para otro jugador.',
      textoConfirmar: 'Cancelar reserva',
      textoVolver: 'No, mantenerla',
      peligro: true
    }))) {
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
        // 409: el turno ya empezó o la reserva ya estaba cancelada
        this.error.set(err.error?.message ?? 'No se pudo cancelar la reserva.');
        this.cancelando.set(null);
      }
    });
  }
}
