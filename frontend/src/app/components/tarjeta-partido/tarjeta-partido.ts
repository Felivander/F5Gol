import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { fechaRelativa, partesFecha, pesos } from '../../formato';
import { PartidoAbierto, textoSuperficie } from '../../models';
import { Icono } from '../icono/icono';

// Tarjeta de un partido de matchmaking que se está armando (lobby e inicio)
@Component({
  selector: 'app-tarjeta-partido',
  imports: [RouterLink, Icono],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './tarjeta-partido.html',
  styleUrl: './tarjeta-partido.css'
})
export class TarjetaPartido {
  partido = input.required<PartidoAbierto>();

  protected fecha = computed(() => partesFecha(this.partido().fecha));
  protected cuando = computed(() => fechaRelativa(this.partido().fecha));
  protected superficie = computed(() => textoSuperficie(this.partido().cancha.superficie));
  protected precio = computed(() => pesos(this.partido().precio_por_jugador));
  // Un punto por lugar: lleno si ya hay alguien anotado
  protected lugares = computed(() =>
    Array.from({ length: this.partido().cupo_maximo }, (_, i) => i < this.partido().anotados)
  );
  protected casiCompleto = computed(() => this.partido().faltan === 1);
}
