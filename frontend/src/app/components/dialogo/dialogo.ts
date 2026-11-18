import { ChangeDetectionStrategy, Component, ElementRef, effect, inject, signal, viewChild } from '@angular/core';
import { Dialogos } from '../../services/dialogos';
import { Icono } from '../icono/icono';

// Diálogo único de la app (confirmaciones y links para copiar). Se usa a través del servicio Dialogos.
// Es un <dialog> nativo: atrapa el foco, cierra con Escape y tapa el resto de la página.
@Component({
  selector: 'app-dialogo',
  imports: [Icono],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './dialogo.html',
  styleUrl: './dialogo.css'
})
export class DialogoComponente {
  protected dialogos = inject(Dialogos);
  private elemento = viewChild.required<ElementRef<HTMLDialogElement>>('dialogo');
  private botonPrincipal = viewChild<ElementRef<HTMLButtonElement>>('principal');

  copiado = signal(false);

  constructor() {
    effect(() => {
      const actual = this.dialogos.actual();
      const dialogo = this.elemento().nativeElement;
      if (actual && !dialogo.open) {
        this.copiado.set(false);
        dialogo.showModal();
        // En una acción que borra algo, el foco arranca en "Volver" y no en el botón rojo
        if (!actual.peligro) {
          queueMicrotask(() => this.botonPrincipal()?.nativeElement.focus());
        }
      } else if (!actual && dialogo.open) {
        dialogo.close();
      }
    });
  }

  responder(respuesta: boolean) {
    this.dialogos.responder(respuesta);
  }

  // Tocar afuera de la caja cierra como "volver"
  tocarFondo(evento: MouseEvent) {
    if (evento.target === this.elemento().nativeElement) {
      this.responder(false);
    }
  }

  async copiar(link: string) {
    try {
      await navigator.clipboard.writeText(link);
      this.copiado.set(true);
    } catch {
      // Sin permiso para el portapapeles: el link queda seleccionado para copiarlo a mano
      this.copiado.set(false);
    }
  }
}
