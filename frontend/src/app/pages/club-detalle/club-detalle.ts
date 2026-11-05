import { Component, OnInit, computed, inject, input, signal } from '@angular/core';
import { CurrencyPipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Cancha, Club, NuevaCancha } from '../../models';
import { Api } from '../../services/api';
import { Auth } from '../../services/auth';

import { CanchaDiagrama } from '../../components/cancha-diagrama/cancha-diagrama';

@Component({
  selector: 'app-club-detalle',
  imports: [RouterLink, CurrencyPipe, FormsModule, CanchaDiagrama],
  templateUrl: './club-detalle.html'
})
export class ClubDetalle implements OnInit {
  private api = inject(Api);
  private auth = inject(Auth);

  // Viene del parámetro de ruta /clubes/:id
  id = input.required<string>();

  club = signal<Club | null>(null);
  error = signal('');

  // Solo el administrador dueño del club puede crear/editar/eliminar canchas
  esAdmin = computed(() => {
    const usuario = this.auth.usuario();
    const club = this.club();
    return !!usuario && !!club && usuario.rol === 'administrador' && usuario.id === club.administrador_id;
  });

  // --- Alta / edición de canchas (modal) ---
  modalAbierto = signal(false);
  canchaEditando = signal<number | null>(null);
  guardando = signal(false);
  errorForm = signal('');
  mensaje = signal('');

  nombre = '';
  tipo = 'futbol5';
  capacidadJugadores = 10;
  descripcion = '';
  tarifa = 0;
  estado = 'activa';

  // --- Canchas eliminadas (restore) ---
  canchasEliminadas = signal<Cancha[]>([]);

  ngOnInit(): void {
    this.cargarClub();
  }

  private cargarClub(): void {
    this.api.getClub(Number(this.id())).subscribe({
      next: club => {
        this.club.set(club);
        if (this.esAdmin()) {
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

  abrirModalNueva(): void {
    this.canchaEditando.set(null);
    this.nombre = '';
    this.tipo = 'futbol5';
    this.capacidadJugadores = 10;
    this.descripcion = '';
    this.tarifa = 0;
    this.estado = 'activa';
    this.errorForm.set('');
    this.modalAbierto.set(true);
  }

  editarCancha(cancha: Cancha): void {
    this.canchaEditando.set(cancha.id);
    this.nombre = cancha.nombre;
    this.tipo = cancha.tipo;
    this.capacidadJugadores = cancha.capacidad_jugadores;
    this.descripcion = cancha.descripcion ?? '';
    this.tarifa = Number(cancha.tarifa);
    this.estado = cancha.estado;
    this.errorForm.set('');
    this.modalAbierto.set(true);
  }

  cerrarModal(): void {
    if (this.guardando()) {
      return;
    }
    this.modalAbierto.set(false);
    this.canchaEditando.set(null);
  }

  guardarCancha(): void {
    this.errorForm.set('');

    const datos: NuevaCancha = {
      club_id: Number(this.id()),
      nombre: this.nombre.trim(),
      tipo: this.tipo,
      capacidad_jugadores: this.capacidadJugadores,
      descripcion: this.descripcion.trim() || null,
      tarifa: this.tarifa,
      estado: this.estado
    };

    if (datos.nombre.length < 2) {
      this.errorForm.set('El nombre debe tener al menos 2 caracteres.');
      return;
    }

    this.guardando.set(true);

    const id = this.canchaEditando();
    const solicitud = id === null
      ? this.api.crearCancha(datos)
      : this.api.actualizarCancha(id, datos);

    solicitud.subscribe({
      next: respuesta => {
        this.mensaje.set(respuesta.message);
        this.guardando.set(false);
        this.modalAbierto.set(false);
        this.canchaEditando.set(null);
        this.cargarClub();
      },
      error: err => {
        console.error('Error al guardar cancha:', err);
        if (err.status === 422) {
          const errores = err.error?.errors ?? {};
          const primerError = Object.values(errores)[0] as string[] | undefined;
          this.errorForm.set(primerError?.[0] ?? err.error?.message ?? 'Revisá los datos ingresados.');
        } else {
          this.errorForm.set('No se pudo guardar la cancha. Verificá la conexión con el servidor.');
        }
        this.guardando.set(false);
      }
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
