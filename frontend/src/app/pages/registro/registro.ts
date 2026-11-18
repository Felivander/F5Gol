import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { BotonGoogle } from '../../components/boton-google/boton-google';
import { Icono } from '../../components/icono/icono';
import { NuevoUsuario } from '../../models';
import { Auth } from '../../services/auth';
import { PATRON_TELEFONO, camposIguales, noSoloEspacios } from '../../validadores';

type Campo = 'rol' | 'name' | 'email' | 'telefono' | 'password' | 'password_confirmation';

@Component({
  selector: 'app-registro',
  imports: [ReactiveFormsModule, RouterLink, BotonGoogle, Icono],
  templateUrl: './registro.html'
})
export class Registro {
  private auth = inject(Auth);
  private router = inject(Router);
  private fb = inject(FormBuilder);

  form = this.fb.nonNullable.group({
    rol: ['jugador' as NuevoUsuario['rol'], Validators.required],
    name: ['', [Validators.required, Validators.maxLength(255), noSoloEspacios]],
    email: ['', [Validators.required, Validators.email, Validators.maxLength(255)]],
    telefono: ['', [Validators.maxLength(30), Validators.pattern(PATRON_TELEFONO)]],
    password: ['', [Validators.required, Validators.minLength(8)]],
    password_confirmation: ['', Validators.required]
  }, { validators: camposIguales('password', 'password_confirmation') });

  error = signal('');
  // Mensajes de validación de Laravel por campo (422)
  erroresServidor = signal<Record<string, string[]>>({});
  enviando = signal(false);

  // Primero el error que mandó Laravel; si no hay, el de las validaciones del formulario
  errorDe(campo: Campo): string {
    const servidor = this.erroresServidor()[campo]?.[0];
    if (servidor) {
      return servidor;
    }

    const control = this.form.controls[campo];
    if (!control.touched) {
      return '';
    }
    if (campo === 'password_confirmation' && this.form.hasError('noCoinciden')) {
      return 'Las contraseñas no coinciden.';
    }
    if (!control.invalid) {
      return '';
    }
    if (control.hasError('required') || control.hasError('soloEspacios')) {
      return 'Este campo es obligatorio.';
    }
    if (control.hasError('email')) {
      return 'Revisá el correo: tiene que ser como nombre@dominio.com.';
    }
    if (control.hasError('minlength')) {
      return 'La contraseña tiene que tener al menos 8 caracteres.';
    }
    if (control.hasError('maxlength')) {
      return `Máximo ${control.getError('maxlength').requiredLength} caracteres.`;
    }
    return control.hasError('pattern') ? 'Solo números, espacios, +, - y paréntesis.' : '';
  }

  registrarme() {
    this.form.markAllAsTouched();
    if (this.form.invalid) {
      return;
    }

    const valores = this.form.getRawValue();
    const datos: NuevoUsuario = {
      ...valores,
      name: valores.name.trim(),
      email: valores.email.trim()
    };

    this.enviando.set(true);
    this.error.set('');
    this.erroresServidor.set({});
    this.auth.register(datos).subscribe({
      next: res => this.router.navigate([res.user.rol === 'administrador' ? '/mis-clubes' : '/clubes']),
      error: err => {
        this.erroresServidor.set(err.error?.errors ?? {});
        this.error.set(err.status === 422 ? 'Revisá los datos marcados.' : (err.error?.message ?? 'No se pudo crear la cuenta.'));
        this.enviando.set(false);
      }
    });
  }
}
