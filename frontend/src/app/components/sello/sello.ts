import { ChangeDetectionStrategy, Component } from '@angular/core';

let siguiente = 0;

/**
 * Sello cuadrado de F5GOL: fondo lima, la pelota grande en la esquina de abajo a la derecha
 * (cortada por el borde) y "F5" blanco con borde verde oscuro arriba a la izquierda.
 * El tamaño sale del ancho que le dé quien lo usa.
 */
@Component({
  selector: 'app-sello',
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './sello.html',
  styleUrl: './sello.css'
})
export class Sello {
  // La pelota necesita su propio id de recorte (puede haber más de un sello en la página)
  protected recorte = `sello-pelota-${++siguiente}`;
}
