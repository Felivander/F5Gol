import { ChangeDetectionStrategy, Component } from '@angular/core';

let siguiente = 0;

/**
 * Sello cuadrado de F5GOL: fondo lima, la pelota cruzando en diagonal (de abajo a la izquierda
 * hacia arriba a la derecha) con la estela de su recorrido, y "F5" con borde blanco para que se lea
 * encima. El tamaño sale del ancho que le dé quien lo usa.
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
