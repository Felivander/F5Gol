import { describe, expect, it } from 'vitest';
import { formacion } from './cancha-lugares';

describe('formacion', () => {
  it('da una posición por lugar, dentro de la cancha y sin repetir', () => {
    for (let n = 1; n <= 11; n++) {
      const posiciones = formacion(n);
      expect(posiciones).toHaveLength(n);
      for (const p of posiciones) {
        expect(p.x).toBeGreaterThan(0);
        expect(p.x).toBeLessThan(100);
        expect(p.y).toBeGreaterThanOrEqual(20);
        expect(p.y).toBeLessThanOrEqual(85);
      }
      expect(new Set(posiciones.map(p => `${p.x}-${p.y}`)).size).toBe(n);
    }
  });

  it('pone al primero en el arco', () => {
    expect(formacion(5)[0]).toEqual({ x: 50, y: 83 });
    expect(formacion(0)).toEqual([]);
  });
});
