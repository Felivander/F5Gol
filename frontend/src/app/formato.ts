// Formatos de fecha y dinero en castellano rioplatense, para mostrar en pantalla

const SEMANA = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

// "2026-11-20" sin corrimiento de zona horaria
function aFecha(fecha: string): Date {
  const [a, m, d] = fecha.slice(0, 10).split('-').map(Number);
  return new Date(a, m - 1, d);
}

export function hoyTexto(): string {
  const f = new Date();
  return `${f.getFullYear()}-${String(f.getMonth() + 1).padStart(2, '0')}-${String(f.getDate()).padStart(2, '0')}`;
}

// Partes para el bloque de fecha de las tarjetas: { semana: 'jue', dia: '20', mes: 'nov' }
export function partesFecha(fecha: string) {
  const f = aFecha(fecha);
  return { semana: SEMANA[f.getDay()], dia: String(f.getDate()), mes: MESES[f.getMonth()] };
}

// "Hoy", "Mañana" o "jue 20 nov"
export function fechaRelativa(fecha: string): string {
  const hoy = aFecha(hoyTexto()).getTime();
  const dias = Math.round((aFecha(fecha).getTime() - hoy) / 86_400_000);
  if (dias === 0) {
    return 'Hoy';
  }
  if (dias === 1) {
    return 'Mañana';
  }
  const p = partesFecha(fecha);
  return `${p.semana} ${p.dia} ${p.mes}`;
}

const PESOS = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS', maximumFractionDigits: 0 });

export function pesos(valor: number | string): string {
  return PESOS.format(Number(valor));
}

const TIPOS: Record<string, string> = { futbol5: 'Fútbol 5', futbol7: 'Fútbol 7', futbol11: 'Fútbol 11' };

// "futbol5" -> "Fútbol 5" (si llega otro texto, se muestra tal cual)
export function textoTipo(tipo: string | null | undefined): string {
  return tipo ? (TIPOS[tipo] ?? tipo) : '';
}
