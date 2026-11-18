import { Component, DestroyRef, OnInit, inject, input, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { Cancha, CanchaInput, SUPERFICIES, Superficie } from '../../models';
import { Icono } from '../../components/icono/icono';
import { Api } from '../../services/api';
import { Auth } from '../../services/auth';
import { noSoloEspacios } from '../../validadores';

// Capacidad sugerida según el tipo de cancha (dos equipos completos)
const CAPACIDAD_POR_TIPO: Record<string, number> = { futbol5: 10, futbol6: 12, futbol7: 14, futbol8: 16, futbol9: 18, futbol11: 22 };

type Campo = 'nombre' | 'capacidad_jugadores' | 'superficie' | 'tarifa' | 'descripcion';

// Alta (/canchas/nueva?club=1) y edición (/canchas/:id/editar) de una cancha, solo para el dueño del club
@Component({
  selector: 'app-cancha-form',
  imports: [ReactiveFormsModule, RouterLink, Icono],
  templateUrl: './cancha-form.html'
})
export class CanchaForm implements OnInit {
  private api = inject(Api);
  private auth = inject(Auth);
  private router = inject(Router);
  private fb = inject(FormBuilder);
  private destroyRef = inject(DestroyRef);

  // Edición: viene el :id de la ruta. Alta: viene ?club=
  id = input<string>();
  club = input<string>();

  form = this.fb.nonNullable.group({
    nombre: ['', [Validators.required, Validators.maxLength(100), noSoloEspacios]],
    tipo: ['futbol5'],
    capacidad_jugadores: [10, [Validators.required, Validators.min(2), Validators.max(22)]],
    superficie: ['cesped_sintetico' as Superficie | null],
    techada: [false],
    iluminacion: [true],
    vestuarios: [false],
    tarifa: [0, [Validators.required, Validators.min(0)]],
    descripcion: ['', [Validators.maxLength(2000)]],
    estado: ['activa' as 'activa' | 'inactiva']
  });

  protected superficies = SUPERFICIES;
  clubId = signal(0);
  // Cancha guardada (en edición, o después del alta): habilita la carga de fotos
  cancha = signal<Cancha | null>(null);
  error = signal('');
  // Mensajes de validación de Laravel por campo (422)
  erroresServidor = signal<Record<string, string[]>>({});
  aviso = signal('');
  guardando = signal(false);
  subiendoFoto = signal(false);

  constructor() {
    // Al cambiar el tipo, se sugiere la capacidad de ese formato (se puede corregir a mano)
    this.form.controls.tipo.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(tipo => {
        const sugerida = CAPACIDAD_POR_TIPO[tipo];
        if (sugerida) {
          this.form.controls.capacidad_jugadores.setValue(sugerida);
        }
      });
  }

  ngOnInit() {
    if (this.auth.usuario()?.rol !== 'administrador') {
      this.router.navigate(['/clubes']);
      return;
    }

    if (this.id()) {
      this.api.getCancha(Number(this.id())).subscribe({
        next: cancha => {
          this.cancha.set(cancha);
          this.clubId.set(cancha.club_id);
          this.form.patchValue({
            nombre: cancha.nombre,
            tipo: cancha.tipo,
            superficie: cancha.superficie ?? null,
            techada: !!cancha.techada,
            iluminacion: !!cancha.iluminacion,
            vestuarios: !!cancha.vestuarios,
            tarifa: Number(cancha.tarifa),
            descripcion: cancha.descripcion ?? '',
            estado: cancha.estado === 'inactiva' ? 'inactiva' : 'activa'
          }, { emitEvent: false });
          // La capacidad va aparte para que el cambio de tipo no la pise
          this.form.controls.capacidad_jugadores.setValue(cancha.capacidad_jugadores);
        },
        error: () => this.error.set('No se encontró la cancha.')
      });
    } else {
      this.clubId.set(Number(this.club()));
    }
  }

  // Primero el error que mandó Laravel; si no hay, el de las validaciones del formulario
  errorDe(campo: Campo): string {
    const servidor = this.erroresServidor()[campo]?.[0];
    if (servidor) {
      return servidor;
    }
    const control = this.form.controls[campo];
    if (!control.invalid || !control.touched) {
      return '';
    }
    if (control.hasError('required') || control.hasError('soloEspacios')) {
      return campo === 'nombre' ? 'Poné un nombre para la cancha, por ejemplo "Cancha 1".' : 'Este dato es obligatorio.';
    }
    if (control.hasError('min')) {
      return campo === 'tarifa' ? 'La tarifa no puede ser negativa.' : 'Tiene que haber al menos 2 jugadores.';
    }
    if (control.hasError('max')) {
      return 'Como máximo 22 jugadores (fútbol 11).';
    }
    if (control.hasError('maxlength')) {
      return `Máximo ${control.getError('maxlength').requiredLength} caracteres.`;
    }
    return '';
  }

  guardar() {
    this.form.markAllAsTouched();
    if (this.form.invalid) {
      return;
    }

    const valores = this.form.getRawValue();
    const datos: CanchaInput = {
      ...valores,
      club_id: this.clubId(),
      nombre: valores.nombre.trim(),
      descripcion: valores.descripcion.trim()
    };

    this.guardando.set(true);
    this.error.set('');
    this.erroresServidor.set({});
    this.aviso.set('');
    const actual = this.cancha();
    const pedido = actual ? this.api.actualizarCancha(actual.id, datos) : this.api.crearCancha(datos);
    pedido.subscribe({
      next: res => {
        this.cancha.set(res.cancha);
        this.aviso.set(actual ? 'Cambios guardados.' : 'Cancha creada. Ya podés agregarle fotos y cargar sus horarios.');
        this.guardando.set(false);
      },
      error: err => {
        // 422: validación, 403: no es su club, 409: desactivar con reservas futuras
        this.erroresServidor.set(err.error?.errors ?? {});
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
