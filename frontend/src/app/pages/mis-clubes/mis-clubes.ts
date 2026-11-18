import { Component, OnInit, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Icono } from '../../components/icono/icono';
import { Club } from '../../models';
import { Api } from '../../services/api';
import { Dialogos } from '../../services/dialogos';

// Clubes del administrador logueado: crear, editar, entrar a gestionar canchas y eliminar
@Component({
  selector: 'app-mis-clubes',
  imports: [RouterLink, Icono],
  templateUrl: './mis-clubes.html',
  styleUrl: './mis-clubes.css'
})
export class MisClubes implements OnInit {
  private api = inject(Api);
  private dialogos = inject(Dialogos);

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

  async eliminar(club: Club): Promise<void> {
    if (!(await this.dialogos.confirmar({
      titulo: `¿Eliminar ${club.nombre}?`,
      mensaje: 'Se borran el club y sus fotos, y no se puede deshacer. Si todavía tiene canchas, primero hay que eliminarlas.',
      textoConfirmar: 'Eliminar club',
      peligro: true
    }))) {
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
