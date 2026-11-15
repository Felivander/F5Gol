import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { BotonGoogle } from '../../components/boton-google/boton-google';
import { Auth } from '../../services/auth';

@Component({
  selector: 'app-login',
  imports: [FormsModule, RouterLink, BotonGoogle],
  templateUrl: './login.html'
})
export class Login {
  private auth = inject(Auth);
  private router = inject(Router);

  email = '';
  password = '';
  // Laravel vuelve a /login?error=google si el ingreso con Google falló o se canceló
  error = signal(
    inject(ActivatedRoute).snapshot.queryParamMap.get('error') === 'google'
      ? 'No se pudo ingresar con Google. Probá de nuevo o usá tu correo y contraseña.'
      : ''
  );
  enviando = signal(false);

  ingresar() {
    this.enviando.set(true);
    this.error.set('');
    this.auth.login(this.email, this.password).subscribe({
      // El administrador entra a su panel; el jugador, a buscar canchas
      next: res => this.router.navigate([res.user.rol === 'administrador' ? '/panel' : '/clubes']),
      error: err => {
        // 401: credenciales incorrectas, 422: errores de validación de Laravel
        this.error.set(err.error?.message ?? 'No se pudo iniciar sesión.');
        this.enviando.set(false);
      }
    });
  }
}
