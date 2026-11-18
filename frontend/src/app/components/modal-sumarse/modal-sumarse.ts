import { ChangeDetectionStrategy, Component, ElementRef, computed, effect, inject, input, output, signal, viewChild } from '@angular/core';
import { pesos } from '../../formato';
import { Equipo, Equipos } from '../../models';
import { Api } from '../../services/api';
import { Auth } from '../../services/auth';
import { Icono } from '../icono/icono';

// Datos del partido que necesita el modal
export interface PartidoParaSumarse {
  turnoId: number;
  canchaId: number;
  fecha: string;
  cuando: string;
  horaInicio: string;
  equipos: Equipos;
  precioPorJugador: number;
  // true: el usuario ya juega y suma amigos; false: se suma él (y puede traer amigos)
  yaAnotado: boolean;
}

/**
 * Modal para sumarse a un partido o sumar amigos: se elige el equipo y cuántos lugares,
 * se ve el total y se confirma. También deja copiar el link para que el amigo se anote solo.
 * Usa <dialog> nativo: atrapa el foco, cierra con Escape y tapa el resto de la página.
 * (La clase es "hoja" y no "modal": la de Bootstrap lo esconde con display: none.)
 */
@Component({
  selector: 'app-modal-sumarse',
  imports: [Icono],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './modal-sumarse.html',
  styleUrl: './modal-sumarse.css'
})
export class ModalSumarse {
  private api = inject(Api);
  private auth = inject(Auth);

  // null = cerrado
  partido = input<PartidoParaSumarse | null>(null);
  equipoInicial = input<Equipo>('A');

  cerrado = output<void>();
  // Mensaje de la API después de reservar
  sumado = output<string>();

  private dialogo = viewChild.required<ElementRef<HTMLDialogElement>>('dialogo');

  equipo = signal<Equipo>('A');
  cantidad = signal(1);
  enviando = signal(false);
  error = signal('');
  copiado = signal(false);

  protected libres = computed(() => this.partido()?.equipos[this.equipo()].faltan ?? 0);
  protected total = computed(() => pesos((this.partido()?.precioPorJugador ?? 0) * this.cantidad()));
  protected precio = computed(() => pesos(this.partido()?.precioPorJugador ?? 0));

  constructor() {
    // Abre o cierra el <dialog> según haya partido, y arranca en el equipo tocado
    effect(() => {
      const p = this.partido();
      const dialogo = this.dialogo().nativeElement;
      if (p) {
        const inicial = this.equipoInicial();
        this.equipo.set(p.equipos[inicial].faltan > 0 ? inicial : inicial === 'A' ? 'B' : 'A');
        this.cantidad.set(1);
        this.error.set('');
        this.copiado.set(false);
        this.enviando.set(false);
        if (!dialogo.open) {
          dialogo.showModal();
        }
      } else if (dialogo.open) {
        dialogo.close();
      }
    });
  }

  elegirEquipo(equipo: Equipo) {
    this.equipo.set(equipo);
    this.cantidad.set(Math.min(this.cantidad(), Math.max(1, this.partido()?.equipos[equipo].faltan ?? 1)));
  }

  cambiarCantidad(delta: number) {
    this.cantidad.set(Math.min(Math.max(1, this.cantidad() + delta), Math.max(1, this.libres())));
  }

  // Cerrar con la X, con "Cancelar", con Escape o tocando afuera
  cerrar() {
    if (!this.enviando()) {
      this.cerrado.emit();
    }
  }

  tocarFondo(evento: MouseEvent) {
    if (evento.target === this.dialogo().nativeElement) {
      this.cerrar();
    }
  }

  async copiarLink() {
    const p = this.partido();
    if (!p) {
      return;
    }
    const link = `${location.origin}/canchas/${p.canchaId}?dia=${p.fecha}`;
    try {
      await navigator.clipboard.writeText(link);
      this.copiado.set(true);
    } catch {
      prompt('Copiá este link para invitar:', link);
    }
  }

  confirmar() {
    const p = this.partido();
    const usuario = this.auth.usuario();
    if (!p || !usuario || this.libres() < this.cantidad()) {
      return;
    }
    this.enviando.set(true);
    this.error.set('');
    this.api.crearReserva({
      turno_id: p.turnoId,
      usuario_id: usuario.id,
      tipo: 'individual',
      equipo: this.equipo(),
      cantidad_jugadores: this.cantidad()
    }).subscribe({
      next: res => {
        this.enviando.set(false);
        this.sumado.emit(res.message);
      },
      error: err => {
        // 409: el equipo se llenó mientras tanto o el partido ya empezó
        this.error.set(err.error?.message ?? 'No se pudo hacer la reserva.');
        this.enviando.set(false);
      }
    });
  }
}
