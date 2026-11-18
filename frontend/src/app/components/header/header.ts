import { Component, computed, inject } from '@angular/core';
import { Router, RouterLink, RouterLinkActive } from '@angular/router';
import { Auth } from '../../services/auth';
import { Icono } from '../icono/icono';

@Component({
  selector: 'app-header',
  imports: [RouterLink, RouterLinkActive, Icono],
  templateUrl: './header.html',
  styleUrl: './header.css'
})
export class Header {
  protected auth = inject(Auth);
  private router = inject(Router);

  protected inicial = computed(() => (this.auth.usuario()?.name ?? '?').trim().charAt(0).toUpperCase());
  protected primerNombre = computed(() => (this.auth.usuario()?.name ?? '').trim().split(/\s+/)[0]);

  salir() {
    this.auth.logout().subscribe({
      complete: () => this.router.navigate(['/clubes']),
      error: () => this.router.navigate(['/clubes'])
    });
  }
}
