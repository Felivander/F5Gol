import { Component, DestroyRef, OnInit, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Icono } from '../../components/icono/icono';
import { TarjetaPartido } from '../../components/tarjeta-partido/tarjeta-partido';
import { fechaRelativa } from '../../formato';
import { PartidoAbierto } from '../../models';
import { Api } from '../../services/api';

const REFRESCO_MS = 15_000;

// Lobby de matchmaking: partidos que se están armando y cuántos lugares les faltan
@Component({
  selector: 'app-partidos',
  imports: [RouterLink, Icono, TarjetaPartido],
  templateUrl: './partidos.html',
  styleUrl: './partidos.css'
})
export class Partidos implements OnInit {
  private api = inject(Api);

  partidos = signal<PartidoAbierto[]>([]);
  cargando = signal(true);
  error = signal('');

  // Filtros (se aplican en el navegador: la lista completa es corta)
  clubElegido = signal(0);
  fechaElegida = signal('');

  // Opciones de los filtros, sacadas de los partidos que hay
  clubes = computed(() => {
    const vistos = new Map<number, string>();
    for (const p of this.partidos()) {
      if (p.club) {
        vistos.set(p.club.id, p.club.nombre);
      }
    }
    return [...vistos].map(([id, nombre]) => ({ id, nombre })).sort((a, b) => a.nombre.localeCompare(b.nombre));
  });

  fechas = computed(() =>
    [...new Set(this.partidos().map(p => p.fecha.slice(0, 10)))].sort().map(valor => ({ valor, texto: fechaRelativa(valor) }))
  );

  filtrados = computed(() =>
    this.partidos().filter(p =>
      (!this.clubElegido() || p.club?.id === this.clubElegido()) &&
      (!this.fechaElegida() || p.fecha.slice(0, 10) === this.fechaElegida())
    )
  );

  // Lugares libres sumando todos los partidos que se ven
  lugaresLibres = computed(() => this.filtrados().reduce((total, p) => total + p.faltan, 0));

  constructor() {
    // Igual que la grilla: el cupo se refresca solo, sin recargar la página
    const refresco = setInterval(() => {
      if (!document.hidden) {
        this.cargar(true);
      }
    }, REFRESCO_MS);
    inject(DestroyRef).onDestroy(() => clearInterval(refresco));
  }

  ngOnInit(): void {
    this.cargar();
  }

  elegirClub(valor: string): void {
    this.clubElegido.set(Number(valor));
  }

  elegirFecha(valor: string): void {
    this.fechaElegida.set(this.fechaElegida() === valor ? '' : valor);
  }

  limpiarFiltros(): void {
    this.clubElegido.set(0);
    this.fechaElegida.set('');
  }

  // silencioso: refresco automático, sin mostrar "Cargando" ni pisar la lista si falla
  cargar(silencioso = false): void {
    if (!silencioso) {
      this.cargando.set(true);
      this.error.set('');
    }
    this.api.getPartidosAbiertos().subscribe({
      next: partidos => {
        this.partidos.set(partidos);
        this.cargando.set(false);
      },
      error: () => {
        if (!silencioso) {
          this.error.set('No se pudieron cargar los partidos. Probá de nuevo en un rato.');
        }
        this.cargando.set(false);
      }
    });
  }
}
