import { describe, expect, it } from 'vitest';
import { formacion } from './cancha-lugares';

describe('formacion', () => {
  it('da una posición por lugar, en la mitad de cada equipo y sin repetir', () => {
    for (let n = 1; n <= 11; n++) {
      for (const lado of ['A', 'B'] as const) {
        const posiciones = formacion(n, lado);
        expect(posiciones).toHaveLength(n);
        for (const p of posiciones) {
          expect(p.y).toBeGreaterThan(10);
          expect(p.y).toBeLessThan(90);
          if (lado === 'A') {
            expect(p.x).toBeLessThan(50);
          } else {
            expect(p.x).toBeGreaterThan(50);
          }
        }
        expect(new Set(posiciones.map(p => `${p.x}-${p.y}`)).size).toBe(n);
      }
    }
  });

  it('pone al primero de cada equipo en su arco', () => {
    expect(formacion(5, 'A')[0]).toEqual({ x: 7, y: 50 });
    expect(formacion(5, 'B')[0]).toEqual({ x: 93, y: 50 });
    expect(formacion(0)).toEqual([]);
  });
});
