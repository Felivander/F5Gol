import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';

// Un lugar del partido: ocupado por un jugador (o por un amigo que anotó) o libre
export interface LugarPartido {
  nombre: string | null;
  soyYo: boolean;
  amigo: boolean;
}

// Posición de un lugar sobre la media cancha, en % del ancho y del alto
interface Ubicado extends LugarPartido {
  x: number;
  y: number;
  numero: number;
  inicial: string;
}

/**
 * Ubica n jugadores como una formación sobre media cancha (el arco abajo):
 * uno al arco y el resto repartido en líneas de hasta 3, del fondo hacia el medio.
 */
export function formacion(n: number): { x: number; y: number }[] {
  if (n <= 0) {
    return [];
  }
  const posiciones = [{ x: 50, y: 83 }];
  const resto = n - 1;
  const lineas = Math.ceil(resto / 3);
  let pendientes = resto;
  for (let l = 0; l < lineas; l++) {
    // Reparte lo más parejo posible entre las líneas que quedan
    const enLinea = Math.ceil(pendientes / (lineas - l));
    pendientes -= enLinea;
    // De la línea del fondo (64%) hacia la mitad de la cancha (30%, o 22% si son muchas líneas)
    const arriba = lineas >= 3 ? 22 : 30;
    const y = lineas === 1 ? 50 : 64 - (l * (64 - arriba)) / (lineas - 1);
    for (let i = 0; i < enLinea; i++) {
      posiciones.push({ x: ((i + 1) * 100) / (enLinea + 1), y });
    }
  }
  return posiciones;
}

@Component({
  selector: 'app-cancha-lugares',
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './cancha-lugares.html',
  styleUrl: './cancha-lugares.css'
})
export class CanchaLugares {
  lugares = input.required<LugarPartido[]>();
  faltan = input(0);

  protected ubicados = computed<Ubicado[]>(() => {
    const lista = this.lugares();
    const posiciones = formacion(lista.length);
    return lista.map((lugar, i) => ({
      ...lugar,
      ...posiciones[i],
      numero: i + 1,
      inicial: lugar.nombre && !lugar.amigo ? lugar.nombre.charAt(0).toUpperCase() : ''
    }));
  });
}
