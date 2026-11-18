import { Component, OnInit, inject, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { Auth } from '../../services/auth';

// Pantalla a la que vuelve Laravel después de Google: /auth/google#token=...
// Guarda el token, pide los datos del usuario y lo manda a su pantalla según el rol.
@Component({
  selector: 'app-auth-google',
  imports: [RouterLink],
  template: `
    <div class="acceso">
      <div class="acceso-tarjeta">
        @if (error()) {
          <p class="alerta alerta-error" role="alert">{{ error() }}</p>
          <a class="btn btn-primary btn-block" routerLink="/login">Volver a ingresar</a>
        } @else {
          <p class="cargando" role="status">Ingresando con Google…</p>
        }
      </div>
    </div>
  `
})
export class AuthGoogle implements OnInit {
  private auth = inject(Auth);
  private router = inject(Router);

  error = signal('');

  ngOnInit(): void {
    const token = new URLSearchParams(window.location.hash.slice(1)).get('token');
    // El token no tiene que quedar en la barra de direcciones ni en el historial
    history.replaceState(null, '', window.location.pathname);

    if (!token) {
      this.error.set('No se recibió la sesión de Google. Probá de nuevo.');
      return;
    }

    this.auth.iniciarConToken(token).subscribe({
      next: usuario => this.router.navigate([usuario.rol === 'administrador' ? '/panel' : '/clubes']),
      error: () => this.error.set('No se pudo iniciar sesión con Google. Probá de nuevo.')
    });
  }
}
