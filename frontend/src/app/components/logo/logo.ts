import { ChangeDetectionStrategy, Component, input } from '@angular/core';

// Cada pelota necesita su propio id de recorte (puede haber dos logos en la misma página)
let siguiente = 0;

/**
 * Logo de F5GOL: la palabra con la "O" reemplazada por una pelota de gajos.
 * fondo = 'oscuro' en la barra negra (letras blancas, borde lima) y 'claro' sobre papel.
 * El tamaño sale del font-size del lugar donde se usa.
 */
@Component({
  selector: 'app-logo',
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './logo.html',
  styleUrl: './logo.css',
  host: { '[class.oscuro]': "fondo() === 'oscuro'" }
})
export class Logo {
  fondo = input<'claro' | 'oscuro'>('claro');

  protected recorte = `pelota-${++siguiente}`;
}
