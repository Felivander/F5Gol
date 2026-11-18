import { Component, OnInit, inject, input, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { Club, ClubInput } from '../../models';
import { Icono } from '../../components/icono/icono';
import { Api } from '../../services/api';
import { Auth } from '../../services/auth';
import { PATRON_TELEFONO, noSoloEspacios } from '../../validadores';

// Alta (/clubes/nuevo) y edición (/clubes/:id/editar) del club, con formulario reactivo
@Component({
  selector: 'app-club-form',
  imports: [ReactiveFormsModule, RouterLink, Icono],
  templateUrl: './club-form.html'
})
export class ClubForm implements OnInit {
  private api = inject(Api);
  private auth = inject(Auth);
  private router = inject(Router);
  private fb = inject(FormBuilder);

  // Edición: viene el :id de la ruta
  id = input<string>();

  form = this.fb.group({
    nombre: ['', [Validators.required, Validators.maxLength(150), noSoloEspacios]],
    direccion: ['', [Validators.maxLength(255)]],
    telefono: ['', [Validators.maxLength(30), Validators.pattern(PATRON_TELEFONO)]],
    descripcion: ['', [Validators.maxLength(2000)]]
  });

  // Club guardado (en edición, o después del alta): habilita las fotos
  club = signal<Club | null>(null);
  error = signal('');
  // Mensajes de validación de Laravel por campo (422)
  erroresServidor = signal<Record<string, string[]>>({});
  aviso = signal('');
  guardando = signal(false);
  subiendoFoto = signal(false);

  ngOnInit(): void {
    const id = this.id();
    if (!id) {
      return;
    }
    this.aviso.set(history.state?.aviso ?? '');
    this.api.getClub(Number(id)).subscribe({
      next: club => {
        if (club.administrador_id !== this.auth.usuario()?.id) {
          this.router.navigate(['/mis-clubes']);
          return;
        }
        this.club.set(club);
        this.form.patchValue({
          nombre: club.nombre,
          direccion: club.direccion ?? '',
          telefono: club.telefono ?? '',
          descripcion: club.descripcion ?? ''
        });
      },
      error: () => this.error.set('No se encontró el club.')
    });
  }

  // Muestra el error del campo si el usuario ya lo tocó o intentó guardar
  errorDe(campo: keyof ClubInput): string {
    const control = this.form.controls[campo];
    const servidor = this.erroresServidor()[campo]?.[0];
    if (servidor) {
      return servidor;
    }
    if (!control.invalid || !(control.touched || control.dirty)) {
      return '';
    }
    if (control.hasError('required') || control.hasError('soloEspacios')) {
      return 'El nombre es obligatorio.';
    }
    if (control.hasError('maxlength')) {
      return `Máximo ${control.getError('maxlength').requiredLength} caracteres.`;
    }
    if (control.hasError('pattern')) {
      return 'Solo números, espacios, +, - y paréntesis.';
    }
    return '';
  }

  guardar(): void {
    this.form.markAllAsTouched();
    if (this.form.invalid) {
      return;
    }

    this.guardando.set(true);
    this.error.set('');
    this.erroresServidor.set({});
    this.aviso.set('');

    const valores = this.form.getRawValue();
    const datos: ClubInput = {
      nombre: valores.nombre!.trim(),
      direccion: valores.direccion?.trim() || null,
      telefono: valores.telefono?.trim() || null,
      descripcion: valores.descripcion?.trim() || null
    };

    const actual = this.club();
    const pedido = actual ? this.api.actualizarClub(actual.id, datos) : this.api.crearClub(datos);
    pedido.subscribe({
      next: respuesta => {
        this.club.set(respuesta.club);
        this.aviso.set(actual ? 'Cambios guardados.' : 'Club creado. Ahora agregale fotos y cargá sus canchas.');
        this.guardando.set(false);
        if (!actual) {
          // La URL pasa a ser la de edición, así un F5 no crea otro club.
          // El aviso viaja en el estado de la navegación porque la pantalla se vuelve a crear.
          this.router.navigate(['/clubes', respuesta.club.id, 'editar'], {
            replaceUrl: true,
            state: { aviso: this.aviso() }
          });
        }
      },
      error: err => {
        this.erroresServidor.set(err.error?.errors ?? {});
        this.error.set(err.status === 422 ? 'Revisá los datos marcados.' : (err.error?.message ?? 'No se pudo guardar el club.'));
        this.guardando.set(false);
      }
    });
  }

  subirFoto(evento: Event): void {
    const campo = evento.target as HTMLInputElement;
    const archivo = campo.files?.[0];
    const club = this.club();
    if (!archivo || !club) {
      return;
    }
    this.subiendoFoto.set(true);
    this.error.set('');
    this.api.subirFotoClub(club.id, archivo).subscribe({
      next: respuesta => {
        this.club.set(respuesta.club);
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

  borrarFoto(indice: number): void {
    const club = this.club();
    if (!club || !confirm('¿Borrar esta foto?')) {
      return;
    }
    this.api.borrarFotoClub(club.id, indice).subscribe({
      next: respuesta => this.club.set(respuesta.club),
      error: err => this.error.set(err.error?.message ?? 'No se pudo borrar la foto.')
    });
  }
}
