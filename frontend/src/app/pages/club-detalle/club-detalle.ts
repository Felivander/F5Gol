import { Component, OnInit, computed, inject, input, signal } from '@angular/core';
import { CurrencyPipe } from '@angular/common';
import { RouterLink } from '@angular/router';
import { Cancha, Club } from '../../models';
import { Api } from '../../services/api';
import { Auth } from '../../services/auth';

import { CanchaDiagrama } from '../../components/cancha-diagrama/cancha-diagrama';

@Component({
  selector: 'app-club-detalle',
  imports: [RouterLink, CurrencyPipe, CanchaDiagrama],
  templateUrl: './club-detalle.html'
})
export class ClubDetalle implements OnInit {
  private api = inject(Api);
  private auth = inject(Auth);

  // Viene del parámetro de ruta /clubes/:id
  id = input.required<string>();
  // ?mover=<id de reserva>: se está eligiendo un turno nuevo para esa reserva
  mover = input<string>();

  club = signal<Club | null>(null);
  error = signal('');

  // Solo el administrador dueño del club puede crear, editar, eliminar y restaurar canchas
  esDueno = computed(() => {
    const usuario = this.auth.usuario();
    const club = this.club();
    return !!usuario && !!club && usuario.rol === 'administrador' && usuario.id === club.administrador_id;
  });

  mensaje = signal('');

  // Canchas eliminadas (soft delete) que el dueño puede restaurar
  canchasEliminadas = signal<Cancha[]>([]);

  ngOnInit(): void {
    this.cargarClub();
  }

  private cargarClub(): void {
    this.api.getClub(Number(this.id())).subscribe({
      next: club => {
        this.club.set(club);
        if (this.esDueno()) {
          this.cargarCanchasEliminadas();
        }
      },
      error: () => this.error.set('No se encontró el club.')
    });
  }

  cargarCanchasEliminadas(): void {
    this.api.getCanchasEliminadas(Number(this.id())).subscribe({
      next: respuesta => this.canchasEliminadas.set(respuesta.data),
      error: () => this.canchasEliminadas.set([])
    });
  }

  eliminarCancha(cancha: Cancha): void {
    const confirmar = confirm(`¿Querés eliminar la cancha "${cancha.nombre}"?`);
    if (!confirmar) {
      return;
    }

    this.error.set('');
    this.mensaje.set('');

    this.api.eliminarCancha(cancha.id).subscribe({
      next: respuesta => {
        this.mensaje.set(respuesta.message);
        this.cargarClub();
        this.cargarCanchasEliminadas();
      },
      error: err => {
        console.error('Error al eliminar cancha:', err);
        this.error.set(err.error?.message ?? 'No se pudo eliminar la cancha.');
      }
    });
  }

  restaurarCancha(cancha: Cancha): void {
    const confirmar = confirm(`¿Querés restaurar la cancha "${cancha.nombre}"?`);
    if (!confirmar) {
      return;
    }

    this.api.restaurarCancha(cancha.id).subscribe({
      next: respuesta => {
        this.mensaje.set(respuesta.message);
        this.cargarClub();
        this.cargarCanchasEliminadas();
      },
      error: err => {
        console.error('Error al restaurar cancha:', err);
        this.error.set(err.error?.message ?? 'No se pudo restaurar la cancha.');
      }
    });
  }
}
