import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { NuevoUsuario } from '../../models';
import { Auth } from '../../services/auth';

@Component({
  selector: 'app-registro',
  imports: [FormsModule, RouterLink],
  templateUrl: './registro.html'
})
export class Registro {
  private auth = inject(Auth);
  private router = inject(Router);

  datos: NuevoUsuario = {
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    rol: 'jugador',
    telefono: ''
  };
  error = signal('');
  // Mensajes de validación de Laravel por campo (422)
  errores = signal<Record<string, string[]>>({});
  enviando = signal(false);

  registrarme() {
    this.enviando.set(true);
    this.error.set('');
    this.errores.set({});
    this.auth.register(this.datos).subscribe({
      next: res => this.router.navigate([res.user.rol === 'administrador' ? '/panel' : '/clubes']),
      error: err => {
        this.errores.set(err.error?.errors ?? {});
        this.error.set(err.status === 422 ? 'Revisá los datos marcados.' : (err.error?.message ?? 'No se pudo crear la cuenta.'));
        this.enviando.set(false);
      }
    });
  }
}
