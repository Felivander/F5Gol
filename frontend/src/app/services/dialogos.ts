import { Injectable, signal } from '@angular/core';

// Lo que muestra el diálogo abierto
export interface Dialogo {
  // confirmar: pregunta con Sí/No · link: muestra un link para copiar
  tipo: 'confirmar' | 'link';
  titulo: string;
  mensaje?: string;
  // Texto del botón principal ("Eliminar cancha", "Cancelar reserva")
  textoConfirmar?: string;
  // Texto del botón para volver atrás ("No, volver")
  textoVolver?: string;
  // Acción que no se deshace: el botón principal va en rojo
  peligro?: boolean;
  link?: string;
}

/**
 * Reemplaza a confirm() y prompt() del navegador por un diálogo con el diseño de la app.
 * Lo dibuja un solo componente (app-dialogo, en app.html); las pantallas solo llaman acá:
 *
 *   if (!(await this.dialogos.confirmar({ titulo: '¿Eliminar la cancha?', textoConfirmar: 'Eliminar', peligro: true }))) return;
 */
@Injectable({ providedIn: 'root' })
export class Dialogos {
  readonly actual = signal<Dialogo | null>(null);
  private resolver?: (respuesta: boolean) => void;

  confirmar(opciones: Omit<Dialogo, 'tipo' | 'link'>): Promise<boolean> {
    return this.abrir({ tipo: 'confirmar', textoConfirmar: 'Aceptar', textoVolver: 'Volver', ...opciones });
  }

  // Para cuando el navegador no deja copiar solo: muestra el link para copiarlo a mano
  mostrarLink(titulo: string, link: string, mensaje?: string): Promise<boolean> {
    return this.abrir({ tipo: 'link', titulo, link, mensaje, textoVolver: 'Listo' });
  }

  // La respuesta del componente: true si confirmó, false si volvió o cerró
  responder(respuesta: boolean) {
    const resolver = this.resolver;
    this.resolver = undefined;
    this.actual.set(null);
    resolver?.(respuesta);
  }

  private abrir(dialogo: Dialogo): Promise<boolean> {
    // Si había otro abierto, se toma como cancelado
    this.resolver?.(false);
    this.actual.set(dialogo);
    return new Promise(resolve => (this.resolver = resolve));
  }
}
