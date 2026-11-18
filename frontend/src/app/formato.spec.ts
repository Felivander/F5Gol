import { describe, expect, it } from 'vitest';
import { fechaRelativa, hoyTexto, partesFecha, pesos, textoTipo } from './formato';

// Fecha AAAA-MM-DD sumando días a hoy (en hora local, como la usa la app)
function enDias(dias: number): string {
  const d = new Date();
  d.setDate(d.getDate() + dias);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

describe('formato', () => {
  it('arma las partes de la fecha sin correrse de día por la zona horaria', () => {
    expect(partesFecha('2026-11-20')).toEqual({ semana: 'vie', dia: '20', mes: 'nov' });
    expect(partesFecha('2026-01-01T00:00:00.000000Z')).toEqual({ semana: 'jue', dia: '1', mes: 'ene' });
  });

  it('dice Hoy y Mañana, y si no el día corto', () => {
    expect(fechaRelativa(hoyTexto())).toBe('Hoy');
    expect(fechaRelativa(enDias(1))).toBe('Mañana');
    const p = partesFecha(enDias(5));
    expect(fechaRelativa(enDias(5))).toBe(`${p.semana} ${p.dia} ${p.mes}`);
  });

  it('muestra los pesos sin decimales y con punto de miles', () => {
    expect(pesos(17000)).toMatch(/^\$\s?17\.000$/);
    expect(pesos('3400.00')).toMatch(/^\$\s?3\.400$/);
  });

  it('traduce el tipo de cancha', () => {
    expect(textoTipo('futbol5')).toBe('Fútbol 5');
    expect(textoTipo('futbol11')).toBe('Fútbol 11');
    expect(textoTipo('paddle')).toBe('paddle');
    expect(textoTipo(null)).toBe('');
  });
});
