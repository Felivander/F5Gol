import { Component, OnInit, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Club } from '../../models';
import { Api } from '../../services/api';

@Component({
  selector: 'app-clubes',
  imports: [RouterLink],
  templateUrl: './clubes.html'
})
export class Clubes implements OnInit {
  private api = inject(Api);

  clubes = signal<Club[]>([]);
  pagina = signal(1);
  ultimaPagina = signal(1);
  cargando = signal(true);
  error = signal('');

  ngOnInit() {
    this.cargar(1);
  }

  cargar(pagina: number) {
    this.cargando.set(true);
    this.api.getClubs(pagina).subscribe({
      next: res => {
        this.clubes.set(res.data);
        this.pagina.set(res.current_page);
        this.ultimaPagina.set(res.last_page);
        this.cargando.set(false);
      },
      error: () => {
        this.error.set('No se pudo conectar con la API. ¿Está corriendo php artisan serve?');
        this.cargando.set(false);
      }
    });
  }
}
