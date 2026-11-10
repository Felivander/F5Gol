import { Component, OnDestroy, OnInit, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Subscription } from 'rxjs';
import { Club } from '../../models';
import { Api } from '../../services/api';

@Component({
  selector: 'app-clubes',
  imports: [RouterLink],
  templateUrl: './clubes.html'
})
export class Clubes implements OnInit, OnDestroy {
  private api = inject(Api);

  clubes = signal<Club[]>([]);
  pagina = signal(1);
  ultimaPagina = signal(1);
  total = signal(0);
  cargando = signal(true);
  error = signal('');

  // Texto del buscador; se manda a la API un rato después de que el usuario deja de escribir
  busqueda = signal('');
  // Búsqueda de los resultados que se ven en pantalla (puede ir atrasada mientras carga la nueva)
  busquedaMostrada = signal('');
  private esperaBusqueda?: ReturnType<typeof setTimeout>;
  private pedido?: Subscription;

  ngOnInit() {
    this.cargar(1);
  }

  ngOnDestroy() {
    clearTimeout(this.esperaBusqueda);
    this.pedido?.unsubscribe();
  }

  buscar(texto: string) {
    this.busqueda.set(texto);
    clearTimeout(this.esperaBusqueda);
    this.esperaBusqueda = setTimeout(() => this.cargar(1), 300);
  }

  limpiarBusqueda() {
    this.busqueda.set('');
    clearTimeout(this.esperaBusqueda);
    this.cargar(1);
  }

  cargar(pagina: number) {
    this.cargando.set(true);
    this.error.set('');
    // Si llega una respuesta vieja después de una búsqueda nueva, se descarta
    this.pedido?.unsubscribe();
    const texto = this.busqueda().trim();
    this.pedido = this.api.getClubs(pagina, texto).subscribe({
      next: res => {
        this.busquedaMostrada.set(texto);
        this.clubes.set(res.data);
        this.pagina.set(res.current_page);
        this.ultimaPagina.set(res.last_page);
        this.total.set(res.total);
        this.cargando.set(false);
      },
      error: () => {
        this.error.set('No se pudo conectar con la API. ¿Está corriendo php artisan serve?');
        this.cargando.set(false);
      }
    });
  }
}
