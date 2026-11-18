import { Component, OnInit, computed, inject, input, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Cancha, Club, textoSuperficie } from '../../models';
import { Api } from '../../services/api';
import { Dialogos } from '../../services/dialogos';
import { Auth } from '../../services/auth';

import { CanchaDiagrama } from '../../components/cancha-diagrama/cancha-diagrama';
import { Icono } from '../../components/icono/icono';
import { pesos, textoTipo } from '../../formato';

@Component({
  selector: 'app-club-detalle',
  imports: [RouterLink, CanchaDiagrama, Icono],
  templateUrl: './club-detalle.html',
  styleUrl: './club-detalle.css'
})
export class ClubDetalle implements OnInit {
  private api = inject(Api);
  private dialogos = inject(Dialogos);
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

  protected textoSuperficie = textoSuperficie;
  protected pesos = pesos;
  protected textoTipo = textoTipo;

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

  async eliminarCancha(cancha: Cancha): Promise<void> {
    const confirmar = await this.dialogos.confirmar({
      titulo: `¿Eliminar ${cancha.nombre}?`,
      mensaje: 'Deja de verse para los jugadores. Queda en "Canchas eliminadas" y la podés restaurar cuando quieras.',
      textoConfirmar: 'Eliminar cancha',
      peligro: true
    });
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
        this.error.set(err.error?.message ?? 'No se pudo eliminar la cancha.');
      }
    });
  }

  async restaurarCancha(cancha: Cancha): Promise<void> {
    const confirmar = await this.dialogos.confirmar({
      titulo: `¿Restaurar ${cancha.nombre}?`,
      mensaje: 'Vuelve a aparecer en el club con sus turnos, y los jugadores la pueden reservar.',
      textoConfirmar: 'Restaurar cancha'
    });
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
        this.error.set(err.error?.message ?? 'No se pudo restaurar la cancha.');
      }
    });
  }
}
