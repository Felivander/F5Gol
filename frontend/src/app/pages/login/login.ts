import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { Auth } from '../../services/auth';

@Component({
  selector: 'app-login',
  imports: [FormsModule],
  templateUrl: './login.html'
})
export class Login {
  private auth = inject(Auth);
  private router = inject(Router);

  email = '';
  password = '';
  error = signal('');
  enviando = signal(false);

  ingresar() {
    this.enviando.set(true);
    this.error.set('');
    this.auth.login(this.email, this.password).subscribe({
      next: () => this.router.navigate(['/clubes']),
      error: err => {
        // 401: credenciales incorrectas, 422: errores de validación de Laravel
        this.error.set(err.error?.message ?? 'No se pudo iniciar sesión.');
        this.enviando.set(false);
      }
    });
  }
}
