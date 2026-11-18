import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { BotonGoogle } from '../../components/boton-google/boton-google';
import { Icono } from '../../components/icono/icono';
import { Auth } from '../../services/auth';
import { noSoloEspacios } from '../../validadores';

// Avisos que llegan por la URL: /login?error=google o /login?sesion=vencida
function avisoInicial(ruta: ActivatedRoute): string {
  const parametros = ruta.snapshot.queryParamMap;
  if (parametros.get('error') === 'google') {
    return 'No se pudo ingresar con Google. Probá de nuevo o usá tu correo y contraseña.';
  }
  if (parametros.get('sesion') === 'vencida') {
    return 'Tu sesión terminó. Volvé a ingresar para seguir.';
  }
  return '';
}

@Component({
  selector: 'app-login',
  imports: [ReactiveFormsModule, RouterLink, BotonGoogle, Icono],
  templateUrl: './login.html'
})
export class Login {
  private auth = inject(Auth);
  private router = inject(Router);
  private fb = inject(FormBuilder);

  form = this.fb.nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', [Validators.required, noSoloEspacios]]
  });

  error = signal(avisoInicial(inject(ActivatedRoute)));
  enviando = signal(false);

  // Mensaje del campo si el usuario ya lo tocó o intentó ingresar
  errorDe(campo: 'email' | 'password'): string {
    const control = this.form.controls[campo];
    if (!control.invalid || !control.touched) {
      return '';
    }
    if (control.hasError('required') || control.hasError('soloEspacios')) {
      return campo === 'email' ? 'Ingresá tu correo electrónico.' : 'Ingresá tu contraseña.';
    }
    return control.hasError('email') ? 'Revisá el correo: tiene que ser como nombre@dominio.com.' : '';
  }

  ingresar() {
    this.form.markAllAsTouched();
    if (this.form.invalid) {
      return;
    }

    const { email, password } = this.form.getRawValue();
    this.enviando.set(true);
    this.error.set('');
    this.auth.login(email.trim(), password).subscribe({
      // El administrador entra a su panel; el jugador, a buscar canchas
      next: res => this.router.navigate([res.user.rol === 'administrador' ? '/panel' : '/clubes']),
      error: err => {
        // 401: credenciales incorrectas, 422: errores de validación de Laravel, 0: la API no responde
        this.error.set(
          err.status === 0
            ? 'No se pudo conectar con el servidor. Probá de nuevo en un rato.'
            : (err.error?.message ?? 'No se pudo iniciar sesión.')
        );
        this.enviando.set(false);
      }
    });
  }
}
