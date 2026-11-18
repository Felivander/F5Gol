import { Component, OnInit, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Club } from '../../models';
import { Api } from '../../services/api';

// Clubes del administrador logueado: crear, editar, entrar a gestionar canchas y eliminar
@Component({
  selector: 'app-mis-clubes',
  imports: [RouterLink],
  templateUrl: './mis-clubes.html'
})
export class MisClubes implements OnInit {
  private api = inject(Api);

  clubes = signal<Club[]>([]);
  cargando = signal(true);
  error = signal('');
  mensaje = signal('');

  ngOnInit(): void {
    this.cargar();
  }

  cargar(): void {
    this.cargando.set(true);
    this.api.getMisClubes().subscribe({
      next: respuesta => {
        this.clubes.set(respuesta.data);
        this.cargando.set(false);
      },
      error: () => {
        this.error.set('No se pudieron cargar tus clubes.');
        this.cargando.set(false);
      }
    });
  }

  eliminar(club: Club): void {
    if (!confirm(`¿Querés eliminar el club "${club.nombre}"? Esta acción no se puede deshacer.`)) {
      return;
    }
    this.error.set('');
    this.mensaje.set('');
    this.api.eliminarClub(club.id).subscribe({
      next: respuesta => {
        this.mensaje.set(respuesta.message);
        this.cargar();
      },
      // 409: el club todavía tiene canchas
      error: err => this.error.set(err.error?.message ?? 'No se pudo eliminar el club.')
    });
  }
}
