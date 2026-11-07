import { Component, OnInit, inject, input, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { Cancha, CanchaInput } from '../../models';
import { Api } from '../../services/api';
import { Auth } from '../../services/auth';

// Alta (/canchas/nueva?club=1) y edición (/canchas/:id/editar) de una cancha, solo para el dueño del club
@Component({
  selector: 'app-cancha-form',
  imports: [FormsModule, RouterLink],
  templateUrl: './cancha-form.html'
})
export class CanchaForm implements OnInit {
  private api = inject(Api);
  private auth = inject(Auth);
  private router = inject(Router);

  // Edición: viene el :id de la ruta. Alta: viene ?club=
  id = input<string>();
  club = input<string>();

  datos: CanchaInput = {
    club_id: 0,
    nombre: '',
    tipo: 'futbol5',
    capacidad_jugadores: 10,
    descripcion: '',
    tarifa: 0,
    estado: 'activa'
  };
  // Cancha guardada (en edición, o después del alta): habilita la carga de fotos
  cancha = signal<Cancha | null>(null);
  error = signal('');
  errores = signal<Record<string, string[]>>({});
  aviso = signal('');
  guardando = signal(false);
  subiendoFoto = signal(false);

  ngOnInit() {
    if (this.auth.usuario()?.rol !== 'administrador') {
      this.router.navigate(['/clubes']);
      return;
    }

    if (this.id()) {
      this.api.getCancha(Number(this.id())).subscribe({
        next: cancha => {
          this.cancha.set(cancha);
          this.datos = {
            club_id: cancha.club_id,
            nombre: cancha.nombre,
            tipo: cancha.tipo,
            capacidad_jugadores: cancha.capacidad_jugadores,
            descripcion: cancha.descripcion ?? '',
            tarifa: Number(cancha.tarifa),
            estado: cancha.estado === 'inactiva' ? 'inactiva' : 'activa'
          };
        },
        error: () => this.error.set('No se encontró la cancha.')
      });
    } else {
      this.datos.club_id = Number(this.club());
    }
  }

  guardar() {
    this.guardando.set(true);
    this.error.set('');
    this.errores.set({});
    this.aviso.set('');
    const actual = this.cancha();
    const pedido = actual ? this.api.actualizarCancha(actual.id, this.datos) : this.api.crearCancha(this.datos);
    pedido.subscribe({
      next: res => {
        this.cancha.set(res.cancha);
        this.aviso.set(actual ? 'Cambios guardados.' : 'Cancha creada. Ya podés agregarle fotos.');
        this.guardando.set(false);
      },
      error: err => {
        // 422: validación, 403: no es su club, 409: desactivar con reservas futuras
        this.errores.set(err.error?.errors ?? {});
        this.error.set(err.status === 422 ? 'Revisá los datos marcados.' : (err.error?.message ?? 'No se pudo guardar la cancha.'));
        this.guardando.set(false);
      }
    });
  }

  subirFoto(evento: Event) {
    const campo = evento.target as HTMLInputElement;
    const archivo = campo.files?.[0];
    const cancha = this.cancha();
    if (!archivo || !cancha) {
      return;
    }
    this.subiendoFoto.set(true);
    this.error.set('');
    this.api.subirFotoCancha(cancha.id, archivo).subscribe({
      next: res => {
        this.cancha.set(res.cancha);
        this.subiendoFoto.set(false);
        campo.value = '';
      },
      error: err => {
        // 422: no es una imagen o pesa más de 4 MB
        this.error.set(err.error?.errors?.foto?.[0] ?? err.error?.message ?? 'No se pudo subir la foto.');
        this.subiendoFoto.set(false);
      }
    });
  }

  borrarFoto(indice: number) {
    const cancha = this.cancha();
    if (!cancha || !confirm('¿Borrar esta foto?')) {
      return;
    }
    this.api.borrarFotoCancha(cancha.id, indice).subscribe({
      next: res => this.cancha.set(res.cancha),
      error: err => this.error.set(err.error?.message ?? 'No se pudo borrar la foto.')
    });
  }
}
