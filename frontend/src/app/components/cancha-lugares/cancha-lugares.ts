import { ChangeDetectionStrategy, Component, computed, input, output } from '@angular/core';

// Un lugar del partido: ocupado por un jugador (o por un amigo que anotó) o libre
export interface LugarPartido {
  nombre: string | null;
  soyYo: boolean;
  amigo: boolean;
}

// Posición de un lugar sobre la cancha, en % del ancho y del alto
interface Ubicado extends LugarPartido {
  x: number;
  y: number;
  numero: number;
  inicial: string;
}

interface Lado {
  letra: 'A' | 'B';
  lugares: Ubicado[];
  faltan: number;
}

/**
 * Ubica n jugadores de un equipo en su mitad de la cancha (el Equipo A ataca hacia la derecha):
 * uno al arco y el resto en líneas de hasta 3, del fondo hacia el medio.
 * Devuelve x e y en % de la cancha entera.
 */
export function formacion(n: number, lado: 'A' | 'B' = 'A'): { x: number; y: number }[] {
  if (n <= 0) {
    return [];
  }
  const posiciones = [{ x: 7, y: 50 }];
  const resto = n - 1;
  const lineas = Math.ceil(resto / 3);
  let pendientes = resto;
  for (let l = 0; l < lineas; l++) {
    // Reparte lo más parejo posible entre las líneas que quedan
    const enLinea = Math.ceil(pendientes / (lineas - l));
    pendientes -= enLinea;
    // De la defensa (20%) hacia la mitad de la cancha (42%)
    const x = lineas === 1 ? 30 : 20 + (l * 22) / (lineas - 1);
    for (let i = 0; i < enLinea; i++) {
      posiciones.push({ x, y: 12 + ((i + 1) * 76) / (enLinea + 1) });
    }
  }
  return lado === 'A' ? posiciones : posiciones.map(p => ({ x: 100 - p.x, y: p.y }));
}

// Dibujo horizontal de la cancha con los dos equipos del partido
@Component({
  selector: 'app-cancha-lugares',
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './cancha-lugares.html',
  styleUrl: './cancha-lugares.css'
})
export class CanchaLugares {
  equipoA = input.required<LugarPartido[]>();
  equipoB = input.required<LugarPartido[]>();
  // Versión chica para las tarjetas del lobby: sin nombres, fichas más chicas
  compacto = input(false);
  // Los lugares libres son botones: al tocar uno se avisa el equipo
  seleccionable = input(false);
  // Equipo del usuario, para marcar su mitad
  miEquipo = input<'A' | 'B' | null>(null);
  // Mientras se guarda una reserva, los lugares libres no se pueden tocar
  ocupado = input(false);

  elegir = output<'A' | 'B'>();

  protected lados = computed<Lado[]>(() => [this.ubicar('A', this.equipoA()), this.ubicar('B', this.equipoB())]);

  private ubicar(letra: 'A' | 'B', lista: LugarPartido[]): Lado {
    const posiciones = formacion(lista.length, letra);
    return {
      letra,
      faltan: lista.filter(l => !l.nombre).length,
      lugares: lista.map((lugar, i) => ({
        ...lugar,
        ...posiciones[i],
        numero: i + 1,
        inicial: lugar.nombre && !lugar.amigo ? lugar.nombre.charAt(0).toUpperCase() : ''
      }))
    };
  }
}
