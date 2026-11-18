import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';

// Íconos de trazo, todos del mismo estilo (24x24, trazo 1.75, color del texto).
// Son decorativos: el texto que los acompaña es el que da el significado.
const TRAZOS: Record<string, string[]> = {
  ubicacion: ['M12 21s-6.5-5.4-6.5-11a6.5 6.5 0 0 1 13 0c0 5.6-6.5 11-6.5 11z', 'M12 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z'],
  telefono: ['M5 4h3l1.5 4-2 1.5a11 11 0 0 0 7 7l1.5-2 4 1.5v3a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z'],
  reloj: ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z', 'M12 7v5l3 2'],
  calendario: ['M4 6h16v14H4z', 'M4 10h16', 'M8 3v4', 'M16 3v4'],
  jugadores: ['M9 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z', 'M2.5 20a6.5 6.5 0 0 1 13 0', 'M16 4.5a3.5 3.5 0 0 1 0 6.5', 'M18 14a6.5 6.5 0 0 1 3.5 6'],
  flecha: ['M5 12h14', 'M13 6l6 6-6 6'],
  volver: ['M19 12H5', 'M11 6l-6 6 6 6'],
  mas: ['M12 5v14', 'M5 12h14'],
  buscar: ['M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14z', 'M20 20l-4-4'],
  techo: ['M3 11l9-6 9 6', 'M5 10v10h14V10'],
  luz: ['M9 18h6', 'M10 21h4', 'M12 3a6 6 0 0 0-3.5 10.9c.6.5 1 1.2 1 2.1h5c0-.9.4-1.6 1-2.1A6 6 0 0 0 12 3z'],
  ducha: ['M4 20V8a4 4 0 0 1 8 0', 'M9 11h6', 'M10 15v.01', 'M13 15v.01', 'M16 15v.01', 'M11.5 18v.01', 'M14.5 18v.01'],
  cesped: ['M3 20h18', 'M6 20c0-4 1-7 2-9', 'M11 20c0-5 .5-8 1-11', 'M16 20c0-4 1-6 2.5-8'],
  alerta: ['M12 3l9.5 17h-19z', 'M12 10v4', 'M12 17v.01'],
  ok: ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z', 'M8 12l3 3 5-6'],
  salir: ['M15 4h4v16h-4', 'M10 16l4-4-4-4', 'M14 12H4'],
  pelota: ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z', 'M12 7l4 3-1.5 4.5h-5L8 10z', 'M12 3v4', 'M16 10l4.5-1.5', 'M14.5 14.5l2.5 4', 'M9.5 14.5L7 18.5', 'M8 10L3.5 8.5'],
  editar: ['M4 20h4L19 9l-4-4L4 16z', 'M13.5 6.5l4 4'],
  basura: ['M4 7h16', 'M9 7V4h6v3', 'M6 7l1 13h10l1-13'],
  candado: ['M6 11h12v10H6z', 'M8.5 11V7.5a3.5 3.5 0 0 1 7 0V11'],
  panel: ['M4 4h7v7H4z', 'M13 4h7v4h-7z', 'M13 10h7v10h-7z', 'M4 13h7v7H4z']
};

@Component({
  selector: 'app-icono',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <svg class="icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false" [style.width.px]="tamano()" [style.height.px]="tamano()">
      @for (d of trazos(); track $index) {
        <path [attr.d]="d" />
      }
    </svg>
  `,
  styles: [':host { display: inline-flex; line-height: 0; }']
})
export class Icono {
  nombre = input.required<string>();
  tamano = input(18);
  protected trazos = computed(() => TRAZOS[this.nombre()] ?? []);
}
